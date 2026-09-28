<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

/** How an announcement reaches voters. */
enum AnnouncementDisplay: string
{
    use EnumHelpers;

    case POPUP = 'POPUP';
    case TICKER = 'TICKER';
    case BOTH = 'BOTH';

    public function label(): string
    {
        return match ($this) {
            self::POPUP => 'Pop-up',
            self::TICKER => 'Scrolling ticker',
            self::BOTH => 'Pop-up and scrolling ticker',
        };
    }

    public function showsPopup(): bool
    {
        return $this !== self::TICKER;
    }

    public function showsTicker(): bool
    {
        return $this !== self::POPUP;
    }
}
