<?php

declare(strict_types=1);

namespace Myth\Courier\Services;

use Closure;
use DateTimeInterface;
use Myth\Courier\Config\Courier as CourierConfig;
use Myth\Courier\DTO\CampaignDTO;
use Myth\Courier\DTO\ContactDTO;
use Myth\Courier\DTO\DripStepDTO;
use Myth\Courier\DTO\SendDTO;
use Myth\Courier\Enums\CampaignStatus;
use Myth\Courier\Enums\CampaignType;
use Myth\Courier\Enums\ContactStatus;
use Myth\Courier\Enums\SendStatus;
use Myth\Courier\Exceptions\CourierValidationException;
use Myth\Courier\Models\CampaignModel;
use Myth\Courier\Models\ContactModel;
use Myth\Courier\Models\DripStepModel;
use Myth\Courier\Models\SendModel;

/**
 * Manages campaign lifecycle and blast delivery.
 */
class CampaignService
{
    public function __construct(
        private readonly CampaignModel $campaignModel,
        private readonly DripStepModel $dripStepModel,
        private readonly SegmentService $segmentService,
        private readonly MailerService $mailerService,
        private readonly SendModel $sendModel,
        private readonly ContactModel $contactModel,
        private readonly CourierConfig $config,
    ) {
    }

    /**
     * Creates a new campaign. Required fields: name, subject, from_name, from_email.
     * Defaults type to 'blast' if not provided.
     *
     * @param array<string, mixed> $data
     *
     * @throws CourierValidationException
     */
    public function create(array $data): CampaignDTO
    {
        foreach (['name', 'subject', 'from_name', 'from_email'] as $field) {
            if (! isset($data[$field]) || (string) $data[$field] === '') {
                throw new CourierValidationException("The {$field} field is required.");
            }
        }

        if (filter_var($data['from_email'], FILTER_VALIDATE_EMAIL) === false) {
            throw new CourierValidationException('The from_email field must be a valid email address.');
        }

        if (! isset($data['type']) || (string) $data['type'] === '') {
            $data['type'] = CampaignType::Blast;
        }

        if (! isset($data['status']) || (string) $data['status'] === '') {
            $data['status'] = CampaignStatus::Draft;
        }

        $id = (int) $this->campaignModel->insert($data);

        return $this->campaignModel->find($id);
    }

    /**
     * Adds a drip step to a drip_sequence campaign.
     * Auto-assigns position = max(existing) + 1 if not provided.
     * Required step fields: view, subject, delay_hours.
     *
     * @param array<string, mixed> $stepData
     *
     * @throws CourierValidationException
     */
    public function addDripStep(int $campaignId, array $stepData): DripStepDTO
    {
        $campaign = $this->campaignModel->find($campaignId);

        if ($campaign === null) {
            throw new CourierValidationException("Campaign {$campaignId} not found.");
        }

        $type = $campaign->type instanceof CampaignType
            ? $campaign->type
            : CampaignType::tryFrom((string) $campaign->type);

        if ($type === null) {
            throw new CourierValidationException('Campaign has an unrecognized type.');
        }

        if ($type !== CampaignType::DripSequence) {
            throw new CourierValidationException('Only drip_sequence campaigns can have drip steps.');
        }

        foreach (['view', 'subject', 'delay_hours'] as $field) {
            if (! isset($stepData[$field]) || (string) $stepData[$field] === '') {
                throw new CourierValidationException("The {$field} field is required for a drip step.");
            }
        }

        if (! isset($stepData['position'])) {
            $count = (int) $this->dripStepModel
                ->where('campaign_id', $campaignId)
                ->countAllResults();

            $stepData['position'] = $count + 1;
        }

        $stepData['campaign_id'] = $campaignId;

        $id = (int) $this->dripStepModel->insert($stepData);

        return $this->dripStepModel->find($id);
    }

    /**
     * Transitions campaign from draft to scheduled.
     * Validates that a view is set before scheduling.
     *
     * @throws CourierValidationException
     */
    public function schedule(int $campaignId, DateTimeInterface $sendAt): void
    {
        $campaign = $this->campaignModel->find($campaignId);

        if ($campaign === null) {
            throw new CourierValidationException("Campaign {$campaignId} not found.");
        }

        $status = $campaign->status instanceof CampaignStatus
            ? $campaign->status
            : CampaignStatus::tryFrom((string) $campaign->status);

        if ($status === null) {
            throw new CourierValidationException('Campaign has an unrecognized status.');
        }

        if ($status !== CampaignStatus::Draft) {
            throw new CourierValidationException('Only draft campaigns can be scheduled.');
        }

        if (! isset($campaign->view) || (string) $campaign->view === '') {
            throw new CourierValidationException('Campaign must have a view set before scheduling.');
        }

        $this->campaignModel->update($campaignId, [
            'status'       => CampaignStatus::Scheduled,
            'scheduled_at' => $sendAt->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Transitions a paused campaign back to scheduled so the send command
     * will pick it up on the next run.
     *
     * @throws CourierValidationException
     */
    public function resume(int $campaignId): void
    {
        $campaign = $this->campaignModel->find($campaignId);

        if ($campaign === null) {
            throw new CourierValidationException("Campaign {$campaignId} not found.");
        }

        $status = $campaign->status instanceof CampaignStatus
            ? $campaign->status
            : CampaignStatus::tryFrom((string) $campaign->status);

        if ($status === null) {
            throw new CourierValidationException('Campaign has an unrecognized status.');
        }

        if ($status !== CampaignStatus::Paused) {
            throw new CourierValidationException('Only paused campaigns can be resumed.');
        }

        $this->campaignModel->update($campaignId, [
            'status' => CampaignStatus::Scheduled,
        ]);
    }

    /**
     * Resolves the full audience for a campaign (subscribed contacts only).
     *
     * Resolution order:
     *   1. If segment_id set: resolve segment
     *   2. If tag_filter set: resolve by tag slugs
     *   3. If both set: intersect (contacts in both)
     *   4. If neither: all subscribed contacts
     *
     * Always applies a final subscribed-status guard.
     *
     * @return list<object>
     */
    public function resolveAudience(CampaignDTO $campaign): array
    {
        $segmentId = $campaign->segment_id ?? null;

        $tagFilter = isset($campaign->tag_filter) && is_array($campaign->tag_filter) && $campaign->tag_filter !== []
            ? $campaign->tag_filter
            : null;

        $excludeCampaignId = $this->blastCampaignId($campaign);

        if ($segmentId !== null && $tagFilter !== null) {
            $bySegment = $this->indexById($this->segmentService->resolve($segmentId, $excludeCampaignId));
            $byTags    = $this->indexById($this->segmentService->resolveByTagSlugs($tagFilter, $excludeCampaignId));
            $contacts  = array_intersect_key($bySegment, $byTags);
        } elseif ($segmentId !== null) {
            $contacts = $this->indexById($this->segmentService->resolve($segmentId, $excludeCampaignId));
        } elseif ($tagFilter !== null) {
            $contacts = $this->indexById($this->segmentService->resolveByTagSlugs($tagFilter, $excludeCampaignId));
        } else {
            $contactModel = $this->contactModel->subscribed();

            if ($excludeCampaignId !== null) {
                $contactModel = $contactModel->excludeSentForCampaign($excludeCampaignId);
            }

            $contacts = $this->indexById($contactModel->findAll());
        }

        // Final subscribed guard
        $contacts = array_filter(
            $contacts,
            static fn (object $c): bool => (
                ($c->status instanceof ContactStatus && $c->status === ContactStatus::Subscribed)
                || (is_string($c->status) && $c->status === ContactStatus::Subscribed->value)
            ),
        );

        return array_values($contacts);
    }

    /**
     * Streams the full audience for a campaign in memory-safe batches.
     * The $callback receives one batch (list<ContactDTO>) per call.
     * Only subscribed contacts are delivered; resolution logic mirrors resolveAudience().
     */
    public function resolveAudienceChunked(CampaignDTO $campaign, Closure $callback): void
    {
        $chunkSize = $this->config->batchSize;
        $segmentId = $campaign->segment_id ?? null;
        $tagFilter = isset($campaign->tag_filter) && is_array($campaign->tag_filter) && $campaign->tag_filter !== []
            ? $campaign->tag_filter
            : null;

        if ($segmentId !== null && $tagFilter !== null) {
            foreach ($this->segmentService->resolveBySegmentAndTagSlugsChunked($segmentId, $tagFilter, $chunkSize) as $chunk) {
                $callback($chunk);
            }

            return;
        }

        if ($segmentId !== null) {
            foreach ($this->segmentService->resolveChunked($segmentId, $chunkSize) as $chunk) {
                $callback($chunk);
            }

            return;
        }

        if ($tagFilter !== null) {
            foreach ($this->segmentService->resolveByTagSlugsChunked($tagFilter, $chunkSize) as $chunk) {
                $callback($chunk);
            }

            return;
        }

        $batch = [];

        $this->contactModel->subscribed()->chunk(
            $chunkSize,
            static function (object $contact) use (&$batch, $chunkSize, $callback): void {
                $batch[] = $contact;
                if (count($batch) === $chunkSize) {
                    $callback($batch);
                    $batch = [];
                }
            },
        );

        if ($batch !== []) {
            $callback($batch);
        }
    }

    /**
     * Prepares Send rows for the given contact batch.
     * Idempotent:
     *   - pending/sent rows are returned as-is (no duplicate insert)
     *   - failed rows are reset to pending (retry)
     *
     * @param list<ContactDTO> $contacts Batch of contacts to prepare (already paginated)
     *
     * @return list<SendDTO>
     */
    public function prepareBatch(CampaignDTO $campaign, array $contacts): array
    {
        $sends = [];

        if ($contacts === []) {
            return [];
        }

        $contactIds   = array_map(static fn (object $c): int => $c->id, $contacts);
        $existingRows = $this->sendModel
            ->where('campaign_id', $campaign->id)
            ->whereIn('contact_id', $contactIds)
            ->findAll();

        $existingMap = [];

        foreach ($existingRows as $row) {
            $existingMap[(int) $row->contact_id] = $row;
        }

        foreach ($contacts as $contact) {
            $existing = $existingMap[(int) $contact->id] ?? null;

            if ($existing !== null) {
                $status = $existing->status instanceof SendStatus
                    ? $existing->status
                    : SendStatus::tryFrom((string) $existing->status);

                if ($status === SendStatus::Pending || $status === SendStatus::Sent) {
                    $sends[] = $existing;

                    continue;
                }

                // Failed or unknown → reset to pending for retry
                $this->sendModel->update($existing->id, ['status' => SendStatus::Pending]);
                $sends[] = $this->sendModel->find($existing->id);

                continue;
            }

            $sends[] = $this->sendModel->createPending((int) $contact->id, $campaign->id, null);
        }

        return $sends;
    }

    /**
     * Sends a batch of Send rows via the mailer.
     * Applies throttle (milliseconds) between each send if configured.
     *
     * @param list<SendDTO> $sends
     *
     * @return array{sent: int, failed: int}
     */
    public function sendBatch(array $sends): array
    {
        $throttleMs    = $this->config->throttleMs;
        $campaignCache = [];
        $sent          = 0;
        $failed        = 0;

        if ($sends === []) {
            return ['sent' => 0, 'failed' => 0];
        }

        $contactIds  = array_unique(array_map(static fn (object $s): int => $s->contact_id, $sends));
        $contactRows = $this->contactModel->whereIn('id', $contactIds)->findAll();
        $contactMap  = [];

        foreach ($contactRows as $c) {
            $contactMap[(int) $c->id] = $c;
        }

        foreach ($sends as $send) {
            $contact = $contactMap[(int) $send->contact_id] ?? null;

            if ($contact === null) {
                $failed++;

                continue;
            }

            $campaignId = (int) $send->campaign_id;

            if (! isset($campaignCache[$campaignId])) {
                $campaignCache[$campaignId] = $this->campaignModel->find($campaignId);
            }

            $result = $this->mailerService->send($contact, $campaignCache[$campaignId], $send);
            $result ? $sent++ : $failed++;

            if ($throttleMs > 0) {
                usleep($throttleMs * 1_000);
            }
        }

        return ['sent' => $sent, 'failed' => $failed];
    }

    /**
     * Returns aggregated send stats for a campaign.
     *
     * @return array{total: int, sent: int, failed: int, opened: int, clicked: int}
     */
    public function getCampaignStats(int $campaignId): array
    {
        $db    = $this->sendModel->db;
        $table = 'courier_sends';

        $rows = $db->table($table)
            ->select('status, COUNT(*) as cnt, COUNT(opened_at) as opened_cnt, COUNT(clicked_at) as clicked_cnt')
            ->where('campaign_id', $campaignId)
            ->groupBy('status')
            ->get()
            ->getResultObject();

        $byStatus = [];
        $opened   = 0;
        $clicked  = 0;

        foreach ($rows as $row) {
            $key            = $row->status instanceof SendStatus ? $row->status->value : (string) $row->status;
            $byStatus[$key] = (int) $row->cnt;
            $opened += (int) $row->opened_cnt;
            $clicked += (int) $row->clicked_cnt;
        }

        return [
            'total'   => array_sum($byStatus),
            'sent'    => $byStatus[SendStatus::Sent->value] ?? 0,
            'failed'  => $byStatus[SendStatus::Failed->value] ?? 0,
            'opened'  => $opened,
            'clicked' => $clicked,
        ];
    }

    /**
     * Returns the campaign id to exclude already-sent contacts for, or null
     * if the campaign is not a Blast (drip sequences must receive every step).
     */
    private function blastCampaignId(CampaignDTO $campaign): ?int
    {
        $type = $campaign->type instanceof CampaignType
            ? $campaign->type
            : CampaignType::tryFrom((string) $campaign->type);

        return $type === CampaignType::Blast ? $campaign->id : null;
    }

    /**
     * @param list<object> $contacts
     *
     * @return array<int, object>
     */
    private function indexById(array $contacts): array
    {
        $indexed = [];

        foreach ($contacts as $contact) {
            $indexed[(int) $contact->id] = $contact;
        }

        return $indexed;
    }
}
