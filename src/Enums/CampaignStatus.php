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

enum CampaignStatus: string
{
    case Draft     = 'draft';
    case Scheduled = 'scheduled';
    case Sending   = 'sending';
    case Sent      = 'sent';
    case Paused    = 'paused';
    case Cancelled = 'cancelled';
}
