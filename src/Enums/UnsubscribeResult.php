<?php

declare(strict_types=1);

namespace Myth\Courier\Enums;

enum UnsubscribeResult
{
    case Success;
    case NotFound;
    case Expired;
}
