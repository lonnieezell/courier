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

enum ContactStatus: string
{
    case Subscribed   = 'subscribed';
    case Unsubscribed = 'unsubscribed';
    case Bounced      = 'bounced';
    case Complained   = 'complained';
}
