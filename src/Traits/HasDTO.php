<?php

declare(strict_types=1);

namespace Myth\Courier\Traits;

use Myth\Courier\DTO\BaseDTO;

/**
 * Wires a Model's afterFind callbacks to convert raw stdClass results into
 * typed DTO instances. Each model using this trait must declare:
 *
 *   protected string $dtoClass = SomeDTO::class;
 *   protected $afterFind       = ['convertToDto'];
 */
trait HasDTO
{
    /**
     * afterFind callback invoked by CI4 after every find operation.
     * Converts singleton and collection results to the model's DTO class.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    protected function convertToDto(array $data): array
    {
        if ($data['data'] === null) {
            return $data;
        }

        if ($data['singleton']) {
            $data['data'] = ($this->dtoClass)::fromObject($data['data']);
        } else {
            $data['data'] = array_map(
                fn (object $row): BaseDTO => ($this->dtoClass)::fromObject($row),
                $data['data'],
            );
        }

        return $data;
    }
}
