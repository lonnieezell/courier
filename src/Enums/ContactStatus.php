<?php

declare(strict_types=1);

namespace Myth\Courier\Enums;

enum ContactStatus: string
{
    case Subscribed   = 'subscribed';
    case Unsubscribed = 'unsubscribed';
    case Bounced      = 'bounced';
    case Complained   = 'complained';
}
