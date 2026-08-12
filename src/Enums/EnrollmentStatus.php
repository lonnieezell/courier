<?php

declare(strict_types=1);

namespace Myth\Courier\Enums;

enum EnrollmentStatus: string
{
    case Active     = 'active';
    case Processing = 'processing';
    case Paused     = 'paused';
    case Completed  = 'completed';
    case Cancelled  = 'cancelled';
    case Failed     = 'failed';
}
