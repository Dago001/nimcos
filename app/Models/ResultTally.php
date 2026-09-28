<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Computed tally. There is deliberately no fillable attribute and no admin edit route. */
class ResultTally extends Model
{
    use HasUuids;

    public $timestamps = false;

    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'votes' => 'integer',
            'rank' => 'integer',
            'is_tied' => 'boolean',
            'is_provisional_winner' => 'boolean',
            'calculated_at' => 'datetime',
        ];
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }

    public function electionPosition(): BelongsTo
    {
        return $this->belongsTo(ElectionPosition::class);
    }
}
