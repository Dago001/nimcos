<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum AuditResult: string
{
    use EnumHelpers;

    case SUCCESS = 'SUCCESS';
    case FAILURE = 'FAILURE';
    case DENIED = 'DENIED';
}
