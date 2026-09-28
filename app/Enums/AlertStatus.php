<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum AlertStatus: string
{
    use EnumHelpers;

    case OPEN = 'OPEN';
    case REVIEWED = 'REVIEWED';
    case DISMISSED = 'DISMISSED';
}
