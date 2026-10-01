<?php

use App\Enums\NisRank;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The NIS rank list gained three ranks above Comptroller of Immigration
 * Service: Comptroller General (CGI), Deputy Comptroller General (DCG) and
 * Assistant Comptroller General (ACG). The CHECK constraint added in
 * 2026_09_29_000200 only allowed the original 15 codes, so it must be
 * recreated with the current, complete list.
 */
return new class extends Migration
{
    public function up(): void
    {
        $values = "'".implode("','", NisRank::values())."'";

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE voters DROP CONSTRAINT IF EXISTS voters_rank_check');
            DB::statement('ALTER TABLE candidates DROP CONSTRAINT IF EXISTS candidates_rank_check');
            DB::statement("ALTER TABLE voters ADD CONSTRAINT voters_rank_check CHECK (rank IN ({$values}))");
            DB::statement("ALTER TABLE candidates ADD CONSTRAINT candidates_rank_check CHECK (rank IN ({$values}))");
        } else {
            // MariaDB / MySQL constraint drop:
            try {
                DB::statement('ALTER TABLE voters DROP CONSTRAINT voters_rank_check');
            } catch (\Throwable) {
                try {
                    DB::statement('ALTER TABLE voters DROP CHECK voters_rank_check');
                } catch (\Throwable) {}
            }
            try {
                DB::statement('ALTER TABLE candidates DROP CONSTRAINT candidates_rank_check');
            } catch (\Throwable) {
                try {
                    DB::statement('ALTER TABLE candidates DROP CHECK candidates_rank_check');
                } catch (\Throwable) {}
            }
            DB::statement("ALTER TABLE voters ADD CONSTRAINT voters_rank_check CHECK (rank IN ({$values}))");
            DB::statement("ALTER TABLE candidates ADD CONSTRAINT candidates_rank_check CHECK (rank IN ({$values}))");
        }
    }

    public function down(): void
    {
        // Reverting to the original 15-rank constraint would break any row
        // already saved with CGI/DCG/ACG, so down() intentionally does nothing
        // beyond what 2026_09_29_000200's own down() already provides.
    }
};
