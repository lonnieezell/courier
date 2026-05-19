<?php

declare(strict_types=1);

namespace Myth\Courier\Config;

class Registrar
{
    public static function Filters(): array
    {
        return [
            'aliases' => [],
        ];
    }

    public static function Migrations(): array
    {
        return [
            'paths' => [realpath(__DIR__ . '/../../Database/Migrations')],
        ];
    }
}
