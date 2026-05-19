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

namespace Myth\Courier\Enums;

enum EnrollmentStatus: string
{
    case Active    = 'active';
    case Paused    = 'paused';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
