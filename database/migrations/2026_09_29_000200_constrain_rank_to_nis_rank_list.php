<?php

use App\Enums\NisRank;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Rank is now chosen from the fixed NIS rank list (Comptroller of Immigration
 * Service down to Immigration Assistant 3), not free text. Existing rows with
 * a rank outside that list (legacy imports, demo data predating this change)
 * are cleared rather than left invalid, since the admin UI can no longer
 * express them; the value remains visible in the audit trail for that import.
 */
return new class extends Migration
{
    public function up(): void
    {
        $values = "'".implode("','", NisRank::values())."'";

        DB::table('voters')->whereNotNull('rank')->whereRaw("rank NOT IN ({$values})")->update(['rank' => null]);
        DB::table('candidates')->whereNotNull('rank')->whereRaw("rank NOT IN ({$values})")->update(['rank' => null]);

        // NULL passes an IN() check (evaluates to UNKNOWN, not FALSE), so both
        // nullable columns are covered without an explicit "OR rank IS NULL".
        DB::statement("ALTER TABLE voters ADD CONSTRAINT voters_rank_check CHECK (rank IN ({$values}))");
        DB::statement("ALTER TABLE candidates ADD CONSTRAINT candidates_rank_check CHECK (rank IN ({$values}))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE candidates DROP CONSTRAINT IF EXISTS candidates_rank_check');
        DB::statement('ALTER TABLE voters DROP CONSTRAINT IF EXISTS voters_rank_check');
    }
};
