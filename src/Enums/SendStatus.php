<?php

declare(strict_types=1);

namespace Myth\Courier\Enums;

enum SendStatus: string
{
    case Pending = 'pending';
    case Sent    = 'sent';
    case Failed  = 'failed';
    case Bounced = 'bounced';
}
