<?php

declare(strict_types=1);

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
