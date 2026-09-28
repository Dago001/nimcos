<?php

namespace App\Models;

use App\Enums\AnnouncementDisplay;
use App\Enums\AnnouncementLevel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Announcement extends Model
{
    use HasUuids;

    protected $fillable = ['title', 'body', 'display', 'level', 'link_url', 'link_label', 'starts_at', 'ends_at', 'is_active'];

    protected function casts(): array
    {
        return [
            'display' => AnnouncementDisplay::class,
            'level' => AnnouncementLevel::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Active and inside its (optional) display window. */
    public function scopeLive(Builder $query): Builder
    {
        $now = now();

        return $query->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', $now));
    }

    /** LIVE, SCHEDULED, EXPIRED or OFF, for the admin list. */
    public function state(): string
    {
        return match (true) {
            ! $this->is_active => 'OFF',
            $this->ends_at !== null && $this->ends_at->isPast() => 'EXPIRED',
            $this->starts_at !== null && $this->starts_at->isFuture() => 'SCHEDULED',
            default => 'LIVE',
        };
    }

    /** Changes whenever the announcement is edited, so a changed pop-up is shown again to viewers who closed it. */
    public function versionKey(): string
    {
        return substr(hash('sha256', $this->id.'|'.$this->updated_at?->timestamp), 0, 16);
    }
}
