<?php

declare(strict_types=1);

namespace Myth\Courier\Services;

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
    public function create(array $data): object
    {
        foreach (['name', 'subject', 'from_name', 'from_email'] as $field) {
            if (! isset($data[$field]) || (string) $data[$field] === '') {
                throw new CourierValidationException("The {$field} field is required.");
            }
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
    public function addDripStep(int $campaignId, array $stepData): object
    {
        $campaign = $this->campaignModel->find($campaignId);

        if ($campaign === null) {
            throw new CourierValidationException("Campaign {$campaignId} not found.");
        }

        $type = $campaign->type instanceof CampaignType ? $campaign->type : CampaignType::from((string) $campaign->type);

        if ($type !== CampaignType::DripSequence) {
            throw new CourierValidationException('Only drip_sequence campaigns can have drip steps.');
        }

        foreach (['view', 'subject', 'delay_hours'] as $field) {
            if (! isset($stepData[$field]) || (string) $stepData[$field] === '') {
                throw new CourierValidationException("The {$field} field is required for a drip step.");
            }
        }

        if (! isset($stepData['position'])) {
            $maxRow = $this->dripStepModel
                ->selectMax('position')
                ->where('campaign_id', $campaignId)
                ->first();

            $stepData['position'] = ($maxRow !== null ? (int) $maxRow->position : 0) + 1;
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
    public function schedule(int $campaignId, \DateTime $sendAt): void
    {
        $campaign = $this->campaignModel->find($campaignId);

        if ($campaign === null) {
            throw new CourierValidationException('Only draft campaigns can be scheduled.');
        }

        $status = $campaign->status instanceof CampaignStatus
            ? $campaign->status
            : CampaignStatus::from((string) $campaign->status);

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
            throw new CourierValidationException('Only paused campaigns can be resumed.');
        }

        $status = $campaign->status instanceof CampaignStatus
            ? $campaign->status
            : CampaignStatus::from((string) $campaign->status);

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
    public function resolveAudience(object $campaign): array
    {
        $contactModel = new ContactModel();

        $segmentId = isset($campaign->segment_id)
            ? (int) $campaign->segment_id
            : null;

        $tagFilter = isset($campaign->tag_filter) && is_array($campaign->tag_filter) && $campaign->tag_filter !== []
            ? $campaign->tag_filter
            : null;

        if ($segmentId !== null && $tagFilter !== null) {
            $bySegment = $this->indexById($this->segmentService->resolve($segmentId));
            $byTags    = $this->indexById($this->segmentService->resolveByTagSlugs($tagFilter));
            $contacts  = array_intersect_key($bySegment, $byTags);
        } elseif ($segmentId !== null) {
            $contacts = $this->indexById($this->segmentService->resolve($segmentId));
        } elseif ($tagFilter !== null) {
            $contacts = $this->indexById($this->segmentService->resolveByTagSlugs($tagFilter));
        } else {
            $contacts = $this->indexById($contactModel->subscribed()->findAll());
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
     * Prepares a batch of Send rows for the given contact slice.
     * Audience must be resolved once externally and passed in.
     *
     * Idempotent:
     *   - pending/sent rows are returned as-is (no duplicate insert)
     *   - failed rows are reset to pending (retry)
     *
     * @param list<object> $contacts Full resolved audience (not yet sliced)
     *
     * @return list<object> Send objects for this batch slice
     */
    public function prepareBatch(object $campaign, array $contacts, int $offset): array
    {
        $batchSize = (int) config('Courier')->batchSize;
        $slice     = array_slice($contacts, $offset, $batchSize);
        $sends     = [];

        foreach ($slice as $contact) {
            $existing = $this->sendModel
                ->where('contact_id', (int) $contact->id)
                ->where('campaign_id', (int) $campaign->id)
                ->first();

            if ($existing !== null) {
                $status = $existing->status instanceof SendStatus
                    ? $existing->status
                    : SendStatus::from((string) $existing->status);

                if ($status === SendStatus::Pending || $status === SendStatus::Sent) {
                    $sends[] = $existing;

                    continue;
                }

                // Failed → reset to pending for retry
                $this->sendModel->update($existing->id, ['status' => SendStatus::Pending]);
                $sends[] = $this->sendModel->find($existing->id);

                continue;
            }

            $sends[] = $this->sendModel->createPending((int) $contact->id, (int) $campaign->id, null);
        }

        return $sends;
    }

    /**
     * Sends a batch of Send rows via the mailer.
     * Applies throttle (milliseconds) between each send if configured.
     *
     * @param list<object> $sends
     *
     * @return array{sent: int, failed: int}
     */
    public function sendBatch(array $sends): array
    {
        $contactModel  = new ContactModel();
        $throttleMs    = (int) config('Courier')->throttleMs;
        $campaignCache = [];
        $sent          = 0;
        $failed        = 0;

        foreach ($sends as $send) {
            $contact = $contactModel->find((int) $send->contact_id);

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
            ->select('status, COUNT(*) as cnt')
            ->where('campaign_id', $campaignId)
            ->groupBy('status')
            ->get()
            ->getResultObject();

        $byStatus = [];

        foreach ($rows as $row) {
            $key            = $row->status instanceof SendStatus ? $row->status->value : (string) $row->status;
            $byStatus[$key] = (int) $row->cnt;
        }

        $total = array_sum($byStatus);

        $opened = (int) $db->table($table)
            ->where('campaign_id', $campaignId)
            ->where('opened_at IS NOT NULL', null, false)
            ->countAllResults();

        $clicked = (int) $db->table($table)
            ->where('campaign_id', $campaignId)
            ->where('clicked_at IS NOT NULL', null, false)
            ->countAllResults();

        return [
            'total'   => $total,
            'sent'    => $byStatus[SendStatus::Sent->value] ?? 0,
            'failed'  => $byStatus[SendStatus::Failed->value] ?? 0,
            'opened'  => $opened,
            'clicked' => $clicked,
        ];
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
