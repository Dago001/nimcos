<?php

namespace App\Models;

use App\Enums\CandidateStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Candidate extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'election_position_id', 'candidate_number', 'surname', 'first_name', 'other_names',
        'service_number', 'rank', 'command', 'biography', 'display_order',
    ];

    protected function casts(): array
    {
        return [
            'status' => CandidateStatus::class,
            'candidate_number' => 'integer',
            'display_order' => 'integer',
            'is_test_data' => 'boolean',
        ];
    }

    public function election(): BelongsTo
    {
        return $this->belongsTo(Election::class);
    }

    public function electionPosition(): BelongsTo
    {
        return $this->belongsTo(ElectionPosition::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(CandidateDocument::class);
    }

    public function displayName(): string
    {
        return trim($this->first_name.' '.($this->other_names ? $this->other_names.' ' : '').$this->surname);
    }

    public function initials(): string
    {
        return mb_strtoupper(mb_substr($this->first_name, 0, 1).mb_substr($this->surname, 0, 1));
    }

    public function isActive(): bool
    {
        return $this->status === CandidateStatus::ACTIVE;
    }
}
