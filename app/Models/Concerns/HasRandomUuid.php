<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Support\Str;

/**
 * Random (version 4) UUID keys for the anonymous side of the secrecy boundary.
 * Laravel's default ordered UUIDs embed a timestamp, which would let key order
 * be matched against election_voters.voted_at.
 */
trait HasRandomUuid
{
    use HasUuids;

    public function newUniqueId(): string
    {
        return (string) Str::uuid();
    }
}
