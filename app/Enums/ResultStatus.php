<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum ResultStatus: string
{
    use EnumHelpers;

    case NOT_CALCULATED = 'NOT_CALCULATED';
    case CALCULATED = 'CALCULATED';
    case VERIFIED = 'VERIFIED';
    case PUBLISHED = 'PUBLISHED';
}
