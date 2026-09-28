<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum VoterEligibility: string
{
    use EnumHelpers;

    case ELIGIBLE = 'ELIGIBLE';
    case INELIGIBLE = 'INELIGIBLE';
}
