<?php

namespace App\Models;

use App\Enums\RecordStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Position extends Model
{
    use HasUuids;

    protected $fillable = ['name', 'description', 'display_order', 'default_seats'];

    protected function casts(): array
    {
        return [
            'status' => RecordStatus::class,
            'display_order' => 'integer',
            'default_seats' => 'integer',
        ];
    }

    public function electionPositions(): HasMany
    {
        return $this->hasMany(ElectionPosition::class);
    }
}
