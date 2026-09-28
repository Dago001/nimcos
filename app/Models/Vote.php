<?php

namespace App\Models;

use App\Models\Concerns\HasRandomUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A single selection on an anonymous ballot. Write-once (enforced by database trigger). */
class Vote extends Model
{
    use HasRandomUuid;

    public $timestamps = false;

    protected $fillable = [];

    public function ballot(): BelongsTo
    {
        return $this->belongsTo(Ballot::class);
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(Candidate::class);
    }
}
