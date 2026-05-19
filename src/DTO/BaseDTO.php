<?php

declare(strict_types=1);

namespace Myth\Courier\DTO;

/**
 * Base class for all Courier DTO objects.
 *
 * Subclasses declare typed public properties matching their database columns.
 * CI4 model casts (enums, JSON) are applied before this runs, so the incoming
 * stdClass already has correctly typed values.
 */
abstract class BaseDTO
{
    /**
     * Hydrates a DTO from a stdClass row returned by CI4's model pipeline.
     * Only properties declared on the DTO are assigned; extra columns are ignored.
     */
    public static function fromObject(object $row): static
    {
        $dto = new static();

        foreach ((array) $row as $key => $value) {
            if (property_exists($dto, $key)) {
                $dto->{$key} = $value;
            }
        }

        return $dto;
    }
}
