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
    ];
    protected array $casts = [
        'status' => 'enum[\Myth\Courier\Enums\EnrollmentStatus]',
    ];
    protected $validationRules = [
        'contact_id'  => 'required|integer',
        'campaign_id' => 'required|integer',
        'status'      => 'permit_empty|in_list[active,paused,completed,cancelled,failed]',
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
            $this->update($enrollment->id, ['status' => EnrollmentStatus::Completed]);

            return;
        }

        $this->update($enrollment->id, [
            'current_step' => $nextStep->position,
            'next_send_at' => date('Y-m-d H:i:s', time() + ((int) $nextStep->delay_hours * 3600)),
            'retry_count'  => 0,
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
            $this->update($enrollment->id, ['status' => EnrollmentStatus::Failed]);
            Events::trigger('courier_enrollment_failed', $enrollment, $errorMessage);

            return;
        }

        $this->update($enrollment->id, [
            'retry_count'  => $newRetryCount,
            'next_send_at' => date('Y-m-d H:i:s', time() + ($retryDelayMinutes * 60)),
        ]);
    }
}
