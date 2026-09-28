<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum CandidateStatus: string
{
    use EnumHelpers;

    case ACTIVE = 'ACTIVE';
    case INACTIVE = 'INACTIVE';
    case WITHDRAWN = 'WITHDRAWN';
    case DISQUALIFIED = 'DISQUALIFIED';
}
