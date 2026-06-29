<?php

declare(strict_types=1);

namespace Myth\Courier\Config;

use Myth\Courier\Filters\CaptureThrottleFilter;
use Myth\Courier\Postal\CourierSuppressionList;
use Myth\Courier\Postal\CourierUnsubscribeUrl;

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

    /**
     * Binds Courier's suppression list and unsubscribe URL resolver into
     * postal's Config\Email so the mailer filters suppressed recipients and
     * injects List-Unsubscribe headers automatically.
     */
    public static function Email(): array
    {
        return [
            'suppressionList' => CourierSuppressionList::class,
            'unsubscribeUrl'  => CourierUnsubscribeUrl::class,
        ];
    }

    public static function Migrations(): array
    {
        return [
            'paths' => [realpath(__DIR__ . '/../../Database/Migrations')],
        ];
    }
}
