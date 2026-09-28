<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum RecordStatus: string
{
    use EnumHelpers;

    case ACTIVE = 'ACTIVE';
    case INACTIVE = 'INACTIVE';
}
