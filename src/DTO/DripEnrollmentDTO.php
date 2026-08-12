<?php

declare(strict_types=1);

namespace Myth\Courier\DTO;

use Myth\Courier\Enums\EnrollmentStatus;

/**
 * Typed representation of a courier_drip_enrollments row.
 */
class DripEnrollmentDTO extends BaseDTO
{
    public int $id;
    public int $contact_id;
    public int $campaign_id;
    public int $current_step;
    public ?string $next_send_at = null;
    public int $retry_count      = 0;
    public ?string $locked_at    = null;
    public EnrollmentStatus $status;
    public string $created_at;
    public string $updated_at;
}
