<?php

namespace App\Enums;

use App\Enums\Concerns\EnumHelpers;

/**
 * Nigeria Immigration Service ranks, from Comptroller of Immigration Service
 * down to Immigration Assistant 3. Used for both voters (NIS staff) and
 * candidates. Order matches the official NIS hierarchy (highest first).
 */
enum NisRank: string
{
    use EnumHelpers;

    case CIS = 'CIS';
    case DCI = 'DCI';
    case ACI = 'ACI';
    case CSI = 'CSI';
    case SI = 'SI';
    case DSI = 'DSI';
    case ASI1 = 'ASI1';
    case ASI2 = 'ASI2';
    case II = 'II';
    case AII = 'AII';
    case CIA = 'CIA';
    case SIA = 'SIA';
    case IA1 = 'IA1';
    case IA2 = 'IA2';
    case IA3 = 'IA3';

    /** Full rank title, e.g. "Comptroller of Immigration Service (CIS)". */
    public function label(): string
    {
        return match ($this) {
            self::CIS => 'Comptroller of Immigration Service (CIS)',
            self::DCI => 'Deputy Comptroller of Immigration (DCI)',
            self::ACI => 'Assistant Comptroller of Immigration (ACI)',
            self::CSI => 'Chief Superintendent of Immigration (CSI)',
            self::SI => 'Superintendent of Immigration (SI)',
            self::DSI => 'Deputy Superintendent of Immigration (DSI)',
            self::ASI1 => 'Assistant Superintendent of Immigration 1 (ASI1)',
            self::ASI2 => 'Assistant Superintendent of Immigration 2 (ASI2)',
            self::II => 'Inspector of Immigration (II)',
            self::AII => 'Assistant Inspector of Immigration (AII)',
            self::CIA => 'Chief Immigration Assistant (CIA)',
            self::SIA => 'Senior Immigration Assistant (SIA)',
            self::IA1 => 'Immigration Assistant 1 (IA1)',
            self::IA2 => 'Immigration Assistant 2 (IA2)',
            self::IA3 => 'Immigration Assistant 3 (IA3)',
        };
    }

    /** Short form for tight spaces (ballot cards), e.g. "DCI". */
    public function shortLabel(): string
    {
        return $this->value;
    }

    /** Best-effort match from free text (imports, legacy data): full title, abbreviation, or "RANK OF X" forms. */
    public static function fromText(?string $value): ?self
    {
        $value = mb_strtoupper(trim((string) $value));
        if ($value === '') {
            return null;
        }

        foreach (self::cases() as $rank) {
            if ($value === $rank->value || $value === mb_strtoupper($rank->label())) {
                return $rank;
            }
        }

        $stripped = trim(str_replace(['RANK OF', 'THE', '.'], '', $value));
        foreach (self::cases() as $rank) {
            $title = mb_strtoupper(preg_replace('/\s*\([A-Z0-9]+\)$/', '', $rank->label()));
            if ($stripped === $title || $stripped === $rank->value) {
                return $rank;
            }
        }

        return null;
    }
}
