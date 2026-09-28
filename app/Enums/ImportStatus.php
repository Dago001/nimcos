<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum ImportStatus: string
{
    use EnumHelpers;

    case PREVIEWED = 'PREVIEWED';
    case PROCESSING = 'PROCESSING';
    case COMPLETED = 'COMPLETED';
    case FAILED = 'FAILED';
    case CANCELLED = 'CANCELLED';
}
