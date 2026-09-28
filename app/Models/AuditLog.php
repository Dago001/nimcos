<?php

namespace App\Models;

use App\Enums\AuditResult;
use Illuminate\Database\Eloquent\Model;

/** Append-only (database trigger). Written only through App\Services\Audit\AuditLogger. */
class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'result' => AuditResult::class,
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
