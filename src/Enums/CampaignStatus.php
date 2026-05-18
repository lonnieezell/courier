<?php

declare(strict_types=1);

namespace Myth\Courier\Enums;

enum CampaignStatus: string
{
    case Draft     = 'draft';
    case Scheduled = 'scheduled';
    case Sent      = 'sent';
    case Cancelled = 'cancelled';
}
