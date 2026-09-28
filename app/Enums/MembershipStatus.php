<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum MembershipStatus: string
{
    use EnumHelpers;

    case ACTIVE = 'ACTIVE';
    case INACTIVE = 'INACTIVE';
    case SUSPENDED = 'SUSPENDED';
    case WITHDRAWN = 'WITHDRAWN';
}
