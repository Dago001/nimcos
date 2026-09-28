<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

class VoterImportError extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    protected $fillable = ['row_number', 'service_number', 'error_type', 'messages', 'raw'];

    protected function casts(): array
    {
        return ['messages' => 'array', 'raw' => 'array'];
    }
}
