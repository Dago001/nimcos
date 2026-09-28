<?php

namespace App\Services\Announcements;

use App\Models\Announcement;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Live announcements for the public and voter pages. Cached briefly because
 * every page view reads them; the cache is cleared whenever one is saved.
 */
class AnnouncementFeed
{
    private const CACHE_KEY = 'announcements.live';

    /** @return Collection<int, Announcement> */
    public function live(): Collection
    {
        // Cache plain rows, not models: the cache store refuses to unserialize objects.
        $rows = Cache::remember(self::CACHE_KEY, 30, fn () => Announcement::query()->live()
            ->orderByRaw("CASE level WHEN 'URGENT' THEN 0 WHEN 'IMPORTANT' THEN 1 ELSE 2 END")
            ->orderByDesc('updated_at')
            ->limit(20)
            ->get()
            ->map(fn (Announcement $a) => $a->getAttributes())
            ->all());

        return Announcement::hydrate($rows)->toBase();
    }

    /** @return Collection<int, Announcement> */
    public function popups(): Collection
    {
        return $this->live()->filter(fn (Announcement $a) => $a->display->showsPopup())->values();
    }

    /** @return Collection<int, Announcement> */
    public function ticker(): Collection
    {
        return $this->live()->filter(fn (Announcement $a) => $a->display->showsTicker())->values();
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
