<?php

declare(strict_types=1);

namespace Myth\Courier\DTO;

/**
 * Typed representation of a courier_segments row.
 */
class SegmentDTO extends BaseDTO
{
    public int $id;
    public string $name;
    public mixed $rules = null;
    public string $match_mode;
    public string $created_at;
    public string $updated_at;
}
