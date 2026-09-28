<?php

namespace App\Support;

/**
 * Nigerian mobile numbers, normalised to E.164 (+234XXXXXXXXXX).
 * Accepts 08031234567, 8031234567, 2348031234567, +234 803 123 4567, 0803-123-4567.
 */
final class PhoneNumber
{
    public static function normalise(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $digits = preg_replace('/\D+/', '', $value) ?? '';
        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '234') && strlen($digits) === 13) {
            $national = substr($digits, 3);
        } elseif (str_starts_with($digits, '0') && strlen($digits) === 11) {
            $national = substr($digits, 1);
        } elseif (strlen($digits) === 10) {
            $national = $digits;
        } else {
            return null;
        }

        // Mobile ranges start with 7, 8 or 9 (070x, 080x, 081x, 090x, 091x ...).
        if (! preg_match('/^[789][01]\d{8}$/', $national)) {
            return null;
        }

        return '+234'.$national;
    }

    public static function display(?string $e164): string
    {
        if (! $e164 || ! str_starts_with($e164, '+234')) {
            return (string) $e164;
        }
        $n = '0'.substr($e164, 4);

        return substr($n, 0, 4).' '.substr($n, 4, 3).' '.substr($n, 7);
    }
}
