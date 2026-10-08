<?php

namespace App\Models;

use App\Enums\ElectionStatus;
use App\Enums\ElectionType;
use App\Enums\EligibilityStatus;
use App\Enums\ResultStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Election extends Model
{
    use HasFactory, HasUuids;

    /** status / result_status / *_by fields change only through ElectionLifecycle. */
    protected $fillable = [
        'name', 'code', 'description', 'election_type', 'starts_at', 'ends_at',
        'auto_open', 'auto_close', 'interim_results_enabled',
    ];

    /** Live vote counts on the dashboard are on unless switched off for an election. */
    protected $attributes = ['interim_results_enabled' => true];

    protected function casts(): array
    {
        return [
            'election_type' => ElectionType::class,
            'status' => ElectionStatus::class,
            'result_status' => ResultStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'scheduled_at' => 'datetime',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'published_at' => 'datetime',
            'archived_at' => 'datetime',
            'auto_open' => 'boolean',
            'auto_close' => 'boolean',
            'interim_results_enabled' => 'boolean',
            'is_test_data' => 'boolean',
        ];
    }

    public function electionPositions(): HasMany
    {
        return $this->hasMany(ElectionPosition::class)->orderBy('display_order');
    }

    public function candidates(): HasMany
    {
        return $this->hasMany(Candidate::class);
    }

    public function electionVoters(): HasMany
    {
        return $this->hasMany(ElectionVoter::class);
    }

    public function resultTallies(): HasMany
    {
        return $this->hasMany(ResultTally::class);
    }

    public function tieResolutions(): HasMany
    {
        return $this->hasMany(TieResolution::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    public function returningOfficerDisplayName(): string
    {
        if (! empty($this->returning_officer_name)) {
            return $this->returning_officer_name;
        }

        if ($this->publisher && ! empty($this->publisher->name)) {
            return $this->publisher->name;
        }

        if (auth()->check() && ! empty(auth()->user()->name)) {
            return auth()->user()->name;
        }

        $returningOfficer = User::query()
            ->whereHas('roles', fn ($query) => $query->where('name', Role::RETURNING_OFFICER))
            ->orderByDesc('last_login_at')
            ->first();

        if ($returningOfficer && ! empty($returningOfficer->name)) {
            return $returningOfficer->name;
        }

        $lastLoggedInAdmin = User::query()
            ->whereNotNull('last_login_at')
            ->orderByDesc('last_login_at')
            ->first();

        if ($lastLoggedInAdmin && ! empty($lastLoggedInAdmin->name)) {
            return $lastLoggedInAdmin->name;
        }

        return 'Returning Officer';
    }

    public function getReturningOfficerDisplayNameAttribute(): string
    {
        return $this->returningOfficerDisplayName();
    }

    /** Ballots may be cast only when status is OPEN *and* the server clock is inside the window. */
    public function isAcceptingVotes(?CarbonInterface $now = null): bool
    {
        $now ??= now();

        return $this->status === ElectionStatus::OPEN
            && $now->greaterThanOrEqualTo($this->starts_at)
            && $now->lessThan($this->ends_at);
    }

    public function scopeAcceptingVotes(Builder $query): Builder
    {
        $now = now();

        return $query->where('status', ElectionStatus::OPEN->value)
            ->where('starts_at', '<=', $now)
            ->where('ends_at', '>', $now);
    }

    /** Admin-visible per-candidate figures: after close, or during voting only with interim results enabled. */
    public function resultsVisibleToAdmins(): bool
    {
        return $this->status->hasClosed()
            || ($this->status === ElectionStatus::OPEN && $this->interim_results_enabled);
    }

    public function eligibleCount(): int
    {
        return $this->electionVoters()
            ->whereIn('eligibility_status', [EligibilityStatus::ELIGIBLE->value, EligibilityStatus::VOTED->value])
            ->count();
    }

    public function votedCount(): int
    {
        return $this->electionVoters()->where('eligibility_status', EligibilityStatus::VOTED->value)->count();
    }

    public function getRouteKeyName(): string
    {
        return 'id';
    }
}
