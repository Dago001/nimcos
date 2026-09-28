<?php

namespace App\Models;

use App\Enums\VotingSessionStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VotingSession extends Model
{
    use HasUuids;

    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'status' => VotingSessionStatus::class,
            'started_at' => 'datetime',
            'last_activity_at' => 'datetime',
            'expires_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function electionVoter(): BelongsTo
    {
        return $this->belongsTo(ElectionVoter::class);
    }

    public function isUsable(): bool
    {
        return $this->status === VotingSessionStatus::ACTIVE && $this->expires_at->isFuture();
    }
}
