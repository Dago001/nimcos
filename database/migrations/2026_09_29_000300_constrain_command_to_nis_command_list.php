<?php

use App\Enums\NisCommand;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Command is now chosen from the official NIS Command directory (every state
 * command, zonal command, land border control post, airport command, marine
 * command and training institution), not free text. Existing rows with a
 * command outside that list (legacy imports, demo data predating this
 * change) are cleared rather than left invalid, since the admin UI can no
 * longer express them.
 */
return new class extends Migration
{
    public function up(): void
    {
        $values = "'".implode("','", NisCommand::values())."'";

        DB::table('voters')->whereNotNull('command')->whereRaw("command NOT IN ({$values})")->update(['command' => null]);
        DB::table('candidates')->whereNotNull('command')->whereRaw("command NOT IN ({$values})")->update(['command' => null]);

        // NULL passes an IN() check (evaluates to UNKNOWN, not FALSE), so both
        // nullable columns are covered without an explicit "OR command IS NULL".
        DB::statement("ALTER TABLE voters ADD CONSTRAINT voters_command_check CHECK (command IN ({$values}))");
        DB::statement("ALTER TABLE candidates ADD CONSTRAINT candidates_command_check CHECK (command IN ({$values}))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE candidates DROP CONSTRAINT IF EXISTS candidates_command_check');
        DB::statement('ALTER TABLE voters DROP CONSTRAINT IF EXISTS voters_command_check');
    }
};
