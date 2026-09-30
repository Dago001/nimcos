<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('voters', function (Blueprint $table) {
            $table->string('membership_id', 50)->nullable()->index()->after('service_number');
            $table->string('gender', 20)->nullable()->after('other_names');
            $table->date('dob')->nullable()->after('gender');
        });

        Schema::table('candidates', function (Blueprint $table) {
            $table->string('membership_id', 50)->nullable()->index()->after('service_number');
        });
    }

    public function down(): void
    {
        Schema::table('voters', function (Blueprint $table) {
            $table->dropIndex(['membership_id']);
            $table->dropColumn(['membership_id', 'gender', 'dob']);
        });

        Schema::table('candidates', function (Blueprint $table) {
            $table->dropIndex(['membership_id']);
            $table->dropColumn(['membership_id']);
        });
    }
};
