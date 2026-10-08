<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('elections', function (Blueprint $table) {
            $table->string('returning_officer_signature', 255)->nullable()->after('published_by');
            $table->string('returning_officer_name', 150)->nullable()->after('returning_officer_signature');
        });

        \Illuminate\Support\Facades\DB::table('system_settings')->updateOrInsert(
            ['key' => 'voting_session_minutes'],
            ['value' => '10', 'updated_at' => now()]
        );
    }

    public function down(): void
    {
        Schema::table('elections', function (Blueprint $table) {
            $table->dropColumn(['returning_officer_signature', 'returning_officer_name']);
        });
    }
};
