<?php

use App\Enums\TieResolutionMethod;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Computed exclusively by App\Services\Results\ResultsCalculator from the votes table.
        Schema::create('result_tallies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('election_id');
            $table->uuid('election_position_id');
            $table->uuid('candidate_id');
            $table->unsignedInteger('votes');
            $table->unsignedInteger('rank');
            $table->boolean('is_tied')->default(false);
            $table->boolean('is_provisional_winner')->default(false);
            $table->string('calculation_hash', 64);
            $table->timestampTz('calculated_at');
            $table->foreignUuid('calculated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->foreign('election_id')->references('id')->on('elections')->restrictOnDelete();
            $table->foreign(['candidate_id', 'election_position_id'])->references(['id', 'election_position_id'])->on('candidates')->restrictOnDelete();
            $table->foreign(['election_position_id', 'election_id'])->references(['id', 'election_id'])->on('election_positions')->restrictOnDelete();
            $table->unique(['election_id', 'candidate_id']);
        });

        Schema::create('tie_resolutions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('election_id');
            $table->uuid('election_position_id');
            $table->string('method', 30);
            $table->uuid('winning_candidate_id')->nullable();
            $table->text('notes');
            $table->foreignUuid('resolved_by')->constrained('users')->restrictOnDelete();
            $table->timestampTz('resolved_at');

            $table->foreign('election_id')->references('id')->on('elections')->restrictOnDelete();
            $table->foreign(['election_position_id', 'election_id'])->references(['id', 'election_id'])->on('election_positions')->restrictOnDelete();
            $table->foreign(['winning_candidate_id', 'election_position_id'], 'fk_tie_res_winning_cand')->references(['id', 'election_position_id'])->on('candidates')->restrictOnDelete();
            $table->unique(['election_id', 'election_position_id']);
        });

        DB::statement('ALTER TABLE tie_resolutions ADD CONSTRAINT tie_resolutions_method_check CHECK ('.TieResolutionMethod::checkSql('method').')');
        DB::statement("ALTER TABLE tie_resolutions ADD CONSTRAINT tie_resolutions_winner_check CHECK (method = 'RUNOFF_PENDING' OR winning_candidate_id IS NOT NULL)");
    }

    public function down(): void
    {
        Schema::dropIfExists('tie_resolutions');
        Schema::dropIfExists('result_tallies');
    }
};
