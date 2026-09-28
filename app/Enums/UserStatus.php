<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum UserStatus: string
{
    use EnumHelpers;

    case ACTIVE = 'ACTIVE';
    case DISABLED = 'DISABLED';
}
