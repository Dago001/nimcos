<?php

namespace App\Models;

use App\Enums\CandidateStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ElectionPosition extends Model
{
    use HasUuids;

    protected $fillable = ['position_id', 'seats', 'display_order', 'is_required'];

    protected function casts(): array
    {
        return [
            'seats' => 'integer',
            'display_order' => 'integer',
            'is_required' => 'boolean',
        ];
    }

    public function election(): BelongsTo
    {
        return $this->belongsTo(Election::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function candidates(): HasMany
    {
        return $this->hasMany(Candidate::class)->orderBy('display_order')->orderBy('candidate_number');
    }

    public function activeCandidates(): HasMany
    {
        return $this->candidates()->where('status', CandidateStatus::ACTIVE->value);
    }

    public function name(): string
    {
        return $this->position->name;
    }
}
