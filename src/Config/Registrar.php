<?php

declare(strict_types=1);

/**
 * This file is part of YourVendor/YourPackage.
 *
 * (c) Your Name <you@example.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

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
