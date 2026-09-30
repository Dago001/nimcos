<?php

use App\Enums\NisCommand;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The NIS Command list gained Service Headquarters (Abuja). The CHECK
 * constraint added in 2026_09_29_000300 only allowed the original 77 codes,
 * so it must be recreated with the current list of 78 commands.
 */
return new class extends Migration
{
    public function up(): void
    {
        $values = "'".implode("','", NisCommand::values())."'";

        DB::statement('ALTER TABLE voters DROP CONSTRAINT IF EXISTS voters_command_check');
        DB::statement('ALTER TABLE candidates DROP CONSTRAINT IF EXISTS candidates_command_check');
        DB::statement("ALTER TABLE voters ADD CONSTRAINT voters_command_check CHECK (command IN ({$values}))");
        DB::statement("ALTER TABLE candidates ADD CONSTRAINT candidates_command_check CHECK (command IN ({$values}))");
    }

    public function down(): void {}
};
