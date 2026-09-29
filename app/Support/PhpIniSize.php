<?php

namespace App\Support;

final class PhpIniSize
{
    /**
     * Parse a php.ini size shorthand (e.g. "20M", "8M", "1G", "512K", "0") into
     * kilobytes. Returns 0 for "0" or an unrecognised/empty value (unlimited or
     * unknown, treated the same way php.ini itself treats "0" as no limit).
     */
    public static function toKb(string $value): int
    {
        $value = trim($value);
        if ($value === '') {
            return 0;
        }
        $unit = strtolower(substr($value, -1));
        $number = (float) $value;

        return (int) match ($unit) {
            'g' => $number * 1024 * 1024,
            'm' => $number * 1024,
            'k' => $number,
            default => is_numeric($value) ? $number / 1024 : 0,
        };
    }

    /** The smaller of upload_max_filesize and post_max_size, in KB (0 = unlimited). */
    public static function effectiveUploadLimitKb(): int
    {
        $upload = self::toKb((string) ini_get('upload_max_filesize'));
        $post = self::toKb((string) ini_get('post_max_size'));
        $limits = array_filter([$upload, $post], fn ($v) => $v > 0);

        return $limits === [] ? 0 : min($limits);
    }
}
