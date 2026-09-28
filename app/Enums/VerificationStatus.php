<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum VerificationStatus: string
{
    use EnumHelpers;

    case UNVERIFIED = 'UNVERIFIED';
    case VERIFIED = 'VERIFIED';
    case REJECTED = 'REJECTED';
}
