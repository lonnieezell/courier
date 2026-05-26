<?php

declare(strict_types=1);

namespace Myth\Courier\Services;

use Myth\Courier\Config\Courier as CourierConfig;
use Myth\Courier\DTO\DripEnrollmentDTO;
use Myth\Courier\DTO\DripStepDTO;
use Myth\Courier\Enums\CampaignType;
use Myth\Courier\Enums\ContactStatus;
use Myth\Courier\Enums\EnrollmentStatus;
use Myth\Courier\Models\CampaignModel;
use Myth\Courier\Models\ContactModel;
use Myth\Courier\Models\DripEnrollmentModel;
use Myth\Courier\Models\DripStepModel;
use RuntimeException;
use Throwable;

/**
 * Manages drip campaign enrollment and step delivery.
 *
 * enroll() behaviour when contact is not subscribed: throws \RuntimeException.
 * Duplicate enrollments (any status) return null silently.
 */
class DripService implements DripServiceInterface
{
    public function __construct(
        private readonly DripEnrollmentModel $enrollmentModel,
        private readonly DripStepModel $stepModel,
        private readonly CampaignModel $campaignModel,
        private readonly MailerService $mailerService,
        private readonly ContactModel $contactModel,
        private readonly CourierConfig $config,
        private readonly CampaignFileLoader $campaignFileLoader = new CampaignFileLoader(),
    ) {
    }

    /**
     * Enrolls a contact in a drip campaign.
     *
     * Returns null if the contact is already enrolled (any status).
     * Throws \RuntimeException if the contact is not subscribed or the campaign has no steps.
     */
    public function enroll(int $contactId, int $campaignId): ?DripEnrollmentDTO
    {
        /** @var object $campaign */
        $campaign = $this->campaignModel->find($campaignId);

        if ($campaign === null || $campaign->type !== CampaignType::DripSequence) {
            throw new RuntimeException('Campaign not found or is not a drip sequence.');
        }

        /** @var object $contact */
        $contact = $this->contactModel->find($contactId);

        if ($contact === null || $contact->status !== ContactStatus::Subscribed) {
            throw new RuntimeException('Contact is not subscribed.');
        }

        $existing = $this->enrollmentModel
            ->where('contact_id', $contactId)
            ->where('campaign_id', $campaignId)
            ->first();

        if ($existing !== null) {
            return null;
        }

        $delayHours = $this->resolveStep1DelayHours($campaign, $campaignId);

        $id = $this->enrollmentModel->insert([
            'contact_id'   => $contactId,
            'campaign_id'  => $campaignId,
            'current_step' => 1,
            'status'       => EnrollmentStatus::Active,
            'next_send_at' => date('Y-m-d H:i:s', time() + ($delayHours * 3600)),
        ]);

        return $this->enrollmentModel->find($id);
    }

    /**
     * Enrolls a contact in a drip campaign looked up by name.
     *
     * Throws \RuntimeException if no campaign with that name exists.
     */
    public function enrollByName(int $contactId, string $name): ?DripEnrollmentDTO
    {
        $campaign = $this->campaignModel->where('name', $name)->first();

        if ($campaign === null) {
            throw new RuntimeException("Campaign not found: '{$name}'.");
        }

        return $this->enroll($contactId, $campaign->id);
    }

    /**
     * Cancels the active enrollment for a contact+campaign pair.
     */
    public function cancel(int $contactId, int $campaignId): void
    {
        $this->enrollmentModel
            ->where('contact_id', $contactId)
            ->where('campaign_id', $campaignId)
            ->where('status', EnrollmentStatus::Active->value)
            ->set('status', EnrollmentStatus::Cancelled)
            ->update();
    }

    /**
     * Cancels all active enrollments for a contact.
     * Called by ContactService::unsubscribeByToken().
     */
    public function cancelAllForContact(int $contactId): void
    {
        $this->enrollmentModel
            ->where('contact_id', $contactId)
            ->where('status', EnrollmentStatus::Active->value)
            ->set('status', EnrollmentStatus::Cancelled)
            ->update();
    }

    /**
     * Sends one batch of due drip steps and advances each enrollment.
     *
     * Intentionally processes at most $config->batchSize enrollments per call to
     * avoid overwhelming the email provider. Schedule this command to run
     * frequently (e.g. every minute via cron) so that the full queue drains over
     * successive invocations rather than in a single unbounded run.
     *
     * Returns counts: ['processed' => n, 'cancelled' => n, 'failed' => n]
     *
     * @return array{processed: int, cancelled: int, failed: int}
     */
    public function processDue(): array
    {
        $enrollments = $this->enrollmentModel
            ->where('status', EnrollmentStatus::Active->value)
            ->where('next_send_at <=', date('Y-m-d H:i:s'))
            ->limit($this->config->batchSize)
            ->findAll();

        if ($enrollments === []) {
            return ['processed' => 0, 'cancelled' => 0, 'failed' => 0];
        }

        // Pre-fetch contacts, campaigns, and steps to avoid N+1 queries
        $contactIds  = array_values(array_unique(array_column($enrollments, 'contact_id')));
        $campaignIds = array_values(array_unique(array_column($enrollments, 'campaign_id')));

        $contactMap = [];

        foreach ($this->contactModel->whereIn('id', $contactIds)->findAll() as $c) {
            $contactMap[$c->id] = $c;
        }

        $campaignMap = [];

        foreach ($this->campaignModel->whereIn('id', $campaignIds)->findAll() as $camp) {
            $campaignMap[$camp->id] = $camp;
        }

        $dbCampaignIds = array_filter($campaignIds, static fn ($id) => ! isset($campaignMap[$id]) || $campaignMap[$id]->source_file === null);
        $stepMap       = [];

        if ($dbCampaignIds !== []) {
            foreach ($this->stepModel->whereIn('campaign_id', array_values($dbCampaignIds))->findAll() as $step) {
                $stepMap[$step->campaign_id][$step->position] = $step;
            }
        }

        $fileStepMap = $this->buildFileStepMap($campaignMap, $campaignIds);

        $processed = 0;
        $cancelled = 0;
        $failed    = 0;

        foreach ($enrollments as $enrollment) {
            $sendSucceeded = false;

            try {
                $contact = $contactMap[$enrollment->contact_id] ?? null;

                if ($contact === null || $contact->status !== ContactStatus::Subscribed) {
                    $this->enrollmentModel->update($enrollment->id, ['status' => EnrollmentStatus::Cancelled]);
                    $cancelled++;

                    continue;
                }

                $campaign    = $campaignMap[$enrollment->campaign_id] ?? null;
                $isFileBased = $campaign !== null && $campaign->source_file !== null;
                $step        = $isFileBased
                    ? ($fileStepMap[$enrollment->campaign_id][$enrollment->current_step] ?? null)
                    : ($stepMap[$enrollment->campaign_id][$enrollment->current_step] ?? null);

                if ($step === null) {
                    $this->enrollmentModel->update($enrollment->id, ['status' => EnrollmentStatus::Cancelled]);
                    $cancelled++;

                    continue;
                }

                $nextStep = $isFileBased
                    ? ($fileStepMap[$enrollment->campaign_id][$enrollment->current_step + 1] ?? null)
                    : ($stepMap[$enrollment->campaign_id][$enrollment->current_step + 1] ?? null);

                if ($this->mailerService->sendStep($contact, $step, $campaign)) {
                    $sendSucceeded = true;
                    $this->enrollmentModel->advance($enrollment, $nextStep);
                    $processed++;
                } else {
                    log_message('error', '[Courier] DripService: failed to send step for enrollment {id}', [
                        'id' => $enrollment->id,
                    ]);
                    $this->enrollmentModel->recordFailure(
                        $enrollment,
                        'mailer returned false',
                        $this->config->retryDelayMinutes,
                        $this->config->maxRetries,
                    );
                    $failed++;
                }
            } catch (Throwable $e) {
                log_message('error', '[Courier] DripService: exception processing enrollment {id}: {message}', [
                    'id'      => $enrollment->id,
                    'message' => $e->getMessage(),
                ]);
                if (! $sendSucceeded) {
                    $this->enrollmentModel->recordFailure(
                        $enrollment,
                        $e->getMessage(),
                        $this->config->retryDelayMinutes,
                        $this->config->maxRetries,
                    );
                }
                $failed++;
            }
        }

        return ['processed' => $processed, 'cancelled' => $cancelled, 'failed' => $failed];
    }

    /**
     * Returns the enrollment object for a contact+campaign pair, or null if none exists.
     */
    public function getEnrollmentStatus(int $contactId, int $campaignId): ?DripEnrollmentDTO
    {
        return $this->enrollmentModel
            ->where('contact_id', $contactId)
            ->where('campaign_id', $campaignId)
            ->first();
    }

    /**
     * Returns step 1's delay_hours, reading from YAML for file-based campaigns
     * or from the DB for standard campaigns.
     */
    private function resolveStep1DelayHours(object $campaign, int $campaignId): int
    {
        if ($campaign->source_file !== null) {
            $parsed = $this->campaignFileLoader->loadCampaignFile($campaign->source_file);

            foreach ($parsed['steps'] as $step) {
                if ((int) $step['position'] === 1) {
                    return (int) $step['delay_hours'];
                }
            }

            throw new RuntimeException('Campaign has no drip steps.');
        }

        $step1 = $this->stepModel
            ->where('campaign_id', $campaignId)
            ->where('position', 1)
            ->first();

        if ($step1 === null) {
            throw new RuntimeException('Campaign has no drip steps.');
        }

        return (int) $step1->delay_hours;
    }

    /**
     * Builds a step map from YAML files for all file-based campaigns.
     *
     * @param array<int, object> $campaignMap
     * @param list<int>          $campaignIds
     *
     * @return array<int, array<int, DripStepDTO>>
     */
    private function buildFileStepMap(array $campaignMap, array $campaignIds): array
    {
        $fileMap = [];

        foreach ($campaignIds as $id) {
            $campaign = $campaignMap[$id] ?? null;

            if ($campaign === null || $campaign->source_file === null) {
                continue;
            }

            try {
                $parsed = $this->campaignFileLoader->loadCampaignFile($campaign->source_file);
            } catch (Throwable $e) {
                log_message('error', '[Courier] DripService: cannot load campaign file {file}: {message}', [
                    'file'    => $campaign->source_file,
                    'message' => $e->getMessage(),
                ]);

                continue;
            }

            foreach ($parsed['steps'] as $stepData) {
                $dto                          = $this->campaignFileLoader->buildDripStepDTO($stepData, $id);
                $fileMap[$id][$dto->position] = $dto;
            }
        }

        return $fileMap;
    }
}
