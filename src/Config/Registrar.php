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
     * postal's Config\Mailer so the mailer filters suppressed recipients and
     * injects List-Unsubscribe headers automatically.
     *
     * CI4 matches a registrar method to the config class' short name, so this
     * must stay named for postal's config class. It was Email() until postal
     * renamed Config\Email to Config\Mailer; a stale name here does not error,
     * it just stops binding.
     */
    public static function Mailer(): array
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
