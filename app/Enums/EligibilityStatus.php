<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum EligibilityStatus: string
{
    use EnumHelpers;

    case ELIGIBLE = 'ELIGIBLE';
    case INELIGIBLE = 'INELIGIBLE';
    case SUSPENDED = 'SUSPENDED';
    case VOTED = 'VOTED';
}
