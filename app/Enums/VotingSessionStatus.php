<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum VotingSessionStatus: string
{
    use EnumHelpers;

    case ACTIVE = 'ACTIVE';
    case COMPLETED = 'COMPLETED';
    case EXPIRED = 'EXPIRED';
    case REVOKED = 'REVOKED';
}
