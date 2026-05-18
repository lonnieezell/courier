<?php

declare(strict_types=1);

namespace Myth\Courier\Enums;

enum EnrollmentStatus: string
{
    case Active    = 'active';
    case Paused    = 'paused';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
