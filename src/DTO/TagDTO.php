<?php

declare(strict_types=1);

namespace Myth\Courier\DTO;

/**
 * Typed representation of a courier_tags row.
 */
class TagDTO extends BaseDTO
{
    public int $id;
    public string $slug;
    public string $label;
    public string $created_at;
    public string $updated_at;
}
