<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum AccountStatus: string
{
    use EnumHelpers;

    case ACTIVE = 'ACTIVE';
    case SUSPENDED = 'SUSPENDED';
    case DISABLED = 'DISABLED';
}
