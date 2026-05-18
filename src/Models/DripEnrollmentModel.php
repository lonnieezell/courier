<?php

declare(strict_types=1);

namespace Myth\Courier\Models;

use CodeIgniter\Model;
use Myth\Courier\Enums\EnrollmentStatus;

/**
 * Manages a contact's enrollment and progress through a drip campaign.
 */
class DripEnrollmentModel extends Model
{
    protected $table         = 'courier_drip_enrollments';
    protected $returnType    = 'object';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'contact_id',
        'campaign_id',
        'current_step',
        'next_send_at',
        'status',
    ];
    protected array $casts = [
        'status' => 'enum[\Myth\Courier\Enums\EnrollmentStatus]',
    ];
    protected $validationRules = [
        'contact_id'  => 'required|integer',
        'campaign_id' => 'required|integer',
        'status'      => 'permit_empty|in_list[active,paused,completed,cancelled]',
    ];

    /**
     * Moves the enrollment to the next drip step.
     * Looks up the step whose position is current_step + 1 within the same campaign.
     * If no next step exists the enrollment is marked completed; otherwise
     * current_step and next_send_at are updated based on the step's delay_hours.
     */
    public function advance(object $enrollment): void
    {
        $nextStep = (new DripStepModel())
            ->where('campaign_id', $enrollment->campaign_id)
            ->where('position', $enrollment->current_step + 1)
            ->first();

        if ($nextStep === null) {
            $this->update($enrollment->id, ['status' => EnrollmentStatus::Completed]);

            return;
        }

        $this->update($enrollment->id, [
            'current_step' => $nextStep->position,
            'next_send_at' => date('Y-m-d H:i:s', strtotime("+{$nextStep->delay_hours} hours")),
        ]);
    }
}
