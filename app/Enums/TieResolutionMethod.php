<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum TieResolutionMethod: string
{
    use EnumHelpers;

    case RUNOFF_PENDING = 'RUNOFF_PENDING';
    case RUNOFF_HELD = 'RUNOFF_HELD';
    case DRAW_OF_LOTS = 'DRAW_OF_LOTS';
    case COMMITTEE_DECISION = 'COMMITTEE_DECISION';
}
