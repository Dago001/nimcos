<?php

namespace App\Models;

use App\Models\Concerns\HasRandomUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Anonymous ballot. Write-once (enforced by database trigger). */
class Ballot extends Model
{
    use HasRandomUuid;

    public $timestamps = false;

    protected $fillable = [];

    public function election(): BelongsTo
    {
        return $this->belongsTo(Election::class);
    }

    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }
}
