<?php

use Carbon\Carbon;
use Carbon\CarbonInterface;

if (! function_exists('display_time')) {
    /**
     * Format a UTC timestamp in the operational timezone (Africa/Lagos).
     */
    function display_time(CarbonInterface|DateTimeInterface|string|null $value, string $format = 'j M Y, H:i'): string
    {
        if ($value === null || $value === '') {
            return '—';
        }
        $carbon = $value instanceof CarbonInterface ? $value->copy() : Carbon::parse($value);

        return $carbon->setTimezone(config('nimcos.display_timezone'))->format($format);
    }
}

if (! function_exists('to_utc_from_display')) {
    /**
     * Interpret an administrator-entered local time (e.g. from datetime-local input) as Africa/Lagos and return UTC.
     */
    function to_utc_from_display(?string $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse($value, config('nimcos.display_timezone'))->utc();
    }
}

if (! function_exists('asset_v')) {
    /** Versioned static asset URL (cache-busted by file modification time). */
    function asset_v(string $path): string
    {
        $full = public_path($path);
        $version = is_file($full) ? filemtime($full) : 0;

        return asset($path).'?v='.$version;
    }
}
