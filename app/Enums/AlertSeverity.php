<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum AlertSeverity: string
{
    use EnumHelpers;

    case LOW = 'LOW';
    case MEDIUM = 'MEDIUM';
    case HIGH = 'HIGH';
    case CRITICAL = 'CRITICAL';
}
