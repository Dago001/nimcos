<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum ElectionType: string
{
    use EnumHelpers;

    case GENERAL = 'GENERAL';
    case BYE_ELECTION = 'BYE_ELECTION';
    case SPECIAL = 'SPECIAL';
}
