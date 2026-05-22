<?php

declare(strict_types=1);

namespace Myth\Courier\Config;

use Myth\Courier\Filters\CaptureThrottleFilter;

class Registrar
{
    public static function Filters(): array
    {
        return [
            'aliases' => [
                'courier_throttle' => CaptureThrottleFilter::class,
            ],
        ];
    }

    public static function Migrations(): array
    {
        return [
            'paths' => [realpath(__DIR__ . '/../../Database/Migrations')],
        ];
    }
}
