<?php

namespace App\Support;

final class ServiceNumber
{
    /** Trim, collapse inner whitespace and upper-case. */
    public static function normalise(?string $value): string
    {
        $value = (string) $value;
        // Strip zero-width and non-breaking spaces commonly pasted from spreadsheets.
        $value = preg_replace('/[\x{00A0}\x{200B}-\x{200D}\x{FEFF}]/u', '', $value) ?? $value;
        $value = preg_replace('/\s+/', '', trim($value)) ?? $value;

        return mb_strtoupper($value);
    }

    public static function isValid(string $normalised): bool
    {
        return (bool) preg_match(config('nimcos.voters.service_number_pattern'), $normalised);
    }
}
