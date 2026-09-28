<?php

namespace App\Enums\Concerns;

trait EnumHelpers
{
    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return ucwords(strtolower(str_replace('_', ' ', $this->value)));
    }

    /** SQL fragment for a CHECK constraint, e.g. "status IN ('A','B')". */
    public static function checkSql(string $column): string
    {
        $quoted = array_map(fn ($v) => "'".$v."'", self::values());

        return $column.' IN ('.implode(',', $quoted).')';
    }
}
