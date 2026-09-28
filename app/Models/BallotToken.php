<?php

namespace App\Models;

use App\Models\Concerns\HasRandomUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** One-time ballot authorisation. Holds no voter, session or time data by design. */
class BallotToken extends Model
{
    use HasRandomUuid;

    public $timestamps = false;

    protected $fillable = [];

    protected function casts(): array
    {
        return ['is_consumed' => 'boolean'];
    }

    public function ballot(): HasOne
    {
        return $this->hasOne(Ballot::class);
    }
}
