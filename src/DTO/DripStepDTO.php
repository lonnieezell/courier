<?php

declare(strict_types=1);

namespace Myth\Courier\DTO;

/**
 * Typed representation of a courier_drip_steps row.
 */
class DripStepDTO extends BaseDTO
{
    public ?int $id = null;
    public int $campaign_id;
    public int $position;
    public string $view;
    public ?string $mailable = null;
    public string $subject;
    public int $delay_hours;
    public string $created_at;
    public string $updated_at;
}
