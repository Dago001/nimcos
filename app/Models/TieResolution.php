<?php

namespace App\Models;

use App\Enums\TieResolutionMethod;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TieResolution extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'method' => TieResolutionMethod::class,
            'resolved_at' => 'datetime',
        ];
    }

    public function winningCandidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class, 'winning_candidate_id');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }
}
