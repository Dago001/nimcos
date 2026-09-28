<?php

namespace App\Models;

use App\Enums\EligibilityStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Election-specific eligibility and participation. Records THAT a voter voted,
 * never WHAT they voted for.
 */
class ElectionVoter extends Model
{
    use HasUuids;

    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'eligibility_status' => EligibilityStatus::class,
            'authorized_at' => 'datetime',
            'voted_at' => 'datetime',
        ];
    }

    public function election(): BelongsTo
    {
        return $this->belongsTo(Election::class);
    }

    public function voter(): BelongsTo
    {
        return $this->belongsTo(Voter::class);
    }

    public function votingSessions(): HasMany
    {
        return $this->hasMany(VotingSession::class);
    }

    public function hasVoted(): bool
    {
        return $this->eligibility_status === EligibilityStatus::VOTED;
    }

    public function canVote(): bool
    {
        return $this->eligibility_status === EligibilityStatus::ELIGIBLE;
    }
}
