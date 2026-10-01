<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Verification codes are now delivered by email, so each voter's email must be
 * unique. Live vote counts on the dashboard are on by default for new elections
 * (the per-election "interim results" switch still turns them off).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE UNIQUE INDEX voters_email_unique ON voters (email) WHERE email IS NOT NULL');
            DB::statement('ALTER TABLE elections ALTER COLUMN interim_results_enabled SET DEFAULT true');
        } else {
            // MySQL unique indexes permit multiple NULLs by default (SQL standard)
            DB::statement('ALTER TABLE voters ADD UNIQUE INDEX voters_email_unique (email)');
            DB::statement('ALTER TABLE elections ALTER interim_results_enabled SET DEFAULT 1');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE elections ALTER COLUMN interim_results_enabled SET DEFAULT false');
            DB::statement('DROP INDEX IF EXISTS voters_email_unique');
        } else {
            DB::statement('ALTER TABLE elections ALTER interim_results_enabled SET DEFAULT 0');
            DB::statement('ALTER TABLE voters DROP INDEX voters_email_unique');
        }
    }
};
