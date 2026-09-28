<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

enum AnnouncementLevel: string
{
    use EnumHelpers;

    case INFO = 'INFO';
    case IMPORTANT = 'IMPORTANT';
    case URGENT = 'URGENT';

    public function label(): string
    {
        return match ($this) {
            self::INFO => 'Information',
            self::IMPORTANT => 'Important',
            self::URGENT => 'Urgent',
        };
    }
}
