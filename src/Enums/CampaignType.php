<?php

declare(strict_types=1);

namespace Myth\Courier\Enums;

enum CampaignType: string
{
    case Blast         = 'blast';
    case DripSequence  = 'drip_sequence';
}
