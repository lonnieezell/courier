<?php

declare(strict_types=1);

namespace Myth\Courier\Models;

use CodeIgniter\Events\Events;
use CodeIgniter\Model;
use Myth\Courier\DTO\DripEnrollmentDTO;
use Myth\Courier\DTO\DripStepDTO;
use Myth\Courier\Enums\EnrollmentStatus;
use Myth\Courier\Traits\HasDTO;

/**
 * Manages a contact's enrollment and progress through a drip campaign.
 */
class DripEnrollmentModel extends Model
{
    use HasDTO;

    protected $table           = 'courier_drip_enrollments';
    protected $returnType      = 'object';
    protected string $dtoClass = DripEnrollmentDTO::class;
    protected $afterFind       = ['convertToDto'];
    protected $useTimestamps   = true;
    protected $allowedFields   = [
        'contact_id',
        'campaign_id',
        'current_step',
        'next_send_at',
        'retry_count',
        'status',
        'locked_at',
    ];
    protected array $casts = [
        'status' => 'enum[\Myth\Courier\Enums\EnrollmentStatus]',
    ];
    protected $validationRules = [
        'contact_id'  => 'required|integer',
        'campaign_id' => 'required|integer',
        'status'      => 'permit_empty|in_list[active,processing,paused,completed,cancelled,failed]',
    ];

    /**
     * Moves the enrollment to the next drip step.
     * Looks up the step whose position is current_step + 1 within the same campaign.
     * If no next step exists the enrollment is marked completed; otherwise
     * current_step and next_send_at are updated based on the step's delay_hours.
     */
    public function advance(DripEnrollmentDTO $enrollment, ?DripStepDTO $nextStep = null): void
    {
        $nextStep ??= model(DripStepModel::class)
            ->where('campaign_id', $enrollment->campaign_id)
            ->where('position', $enrollment->current_step + 1)
            ->first();

        if ($nextStep === null) {
            $this->update($enrollment->id, [
                'status'      => EnrollmentStatus::Completed,
                'retry_count' => 0,
                'locked_at'   => null,
            ]);

            return;
        }

        $this->update($enrollment->id, [
            'status'       => EnrollmentStatus::Active,
            'current_step' => $nextStep->position,
            'next_send_at' => date('Y-m-d H:i:s', time() + ((int) $nextStep->delay_hours * 3600)),
            'retry_count'  => 0,
            'locked_at'    => null,
        ]);
    }

    /**
     * Records a failed send attempt for an enrollment.
     * Increments retry_count and pushes next_send_at forward by $retryDelayMinutes.
     * When retry_count reaches $maxRetries the enrollment is marked Failed and a
     * courier_enrollment_failed event is fired with the enrollment and error message.
     */
    public function recordFailure(
        DripEnrollmentDTO $enrollment,
        string $errorMessage,
        int $retryDelayMinutes,
        int $maxRetries,
    ): void {
        $newRetryCount = $enrollment->retry_count + 1;

        if ($newRetryCount >= $maxRetries) {
            $this->update($enrollment->id, [
                'status'      => EnrollmentStatus::Failed,
                'retry_count' => $newRetryCount,
                'locked_at'   => null,
            ]);
            $enrollment->status      = EnrollmentStatus::Failed;
            $enrollment->retry_count = $newRetryCount;
            Events::trigger('courier_enrollment_failed', $enrollment, $errorMessage);

            return;
        }

        $this->update($enrollment->id, [
            'status'       => EnrollmentStatus::Active,
            'retry_count'  => $newRetryCount,
            'next_send_at' => date('Y-m-d H:i:s', time() + ($retryDelayMinutes * 60)),
            'locked_at'    => null,
        ]);
    }

    /**
     * Atomically claims up to $batchSize due, active enrollments by flipping
     * them to `processing`. Only rows this call actually wins (verified via
     * per-row affected-row checks) are returned, so an overlapping run cannot
     * claim the same enrollment twice.
     *
     * @return list<DripEnrollmentDTO>
     */
    public function claimDue(int $batchSize): array
    {
        $candidates = $this->select('id')
            ->where('status', EnrollmentStatus::Active->value)
            ->where('next_send_at <=', date('Y-m-d H:i:s'))
            ->limit($batchSize)
            ->findAll();

        $now        = date('Y-m-d H:i:s');
        $claimedIds = [];

        foreach ($candidates as $candidate) {
            $this->where('id', $candidate->id)
                ->where('status', EnrollmentStatus::Active->value)
                ->set(['status' => EnrollmentStatus::Processing, 'locked_at' => $now])
                ->update();

            if ($this->db->affectedRows() === 1) {
                $claimedIds[] = $candidate->id;
            }
        }

        if ($claimedIds === []) {
            return [];
        }

        return $this->whereIn('id', $claimedIds)->findAll();
    }

    /**
     * Returns enrollments stuck in `processing` past $staleMinutes back to
     * `active`, so a crashed or killed courier:process-drips run does not
     * strand them permanently.
     */
    public function reclaimStale(int $staleMinutes): void
    {
        $threshold = date('Y-m-d H:i:s', time() - $staleMinutes * 60);

        $this->where('status', EnrollmentStatus::Processing->value)
            ->where('locked_at <=', $threshold)
            ->set(['status' => EnrollmentStatus::Active, 'locked_at' => null])
            ->update();
    }
}
