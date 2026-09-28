<?php

use App\Enums\VotingSessionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * SECRECY BOUNDARY
 * ----------------
 * otp_verifications and voting_sessions know WHO is voting.
 * ballot_tokens, ballots and votes know WHAT was chosen.
 * No column, foreign key or timestamp joins the two sides (see docs/ARCHITECTURE.md §7).
 * ballot_tokens / ballots / votes deliberately have NO timestamps and use random
 * (v4) UUIDs so that neither key order nor time can be matched to election_voters.voted_at.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('otp_verifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('voter_id')->constrained('voters')->cascadeOnDelete();
            $table->string('channel', 10);                  // SMS, EMAIL
            $table->string('destination_masked', 60);
            $table->string('code_hash', 64);                // HMAC-SHA256, never the plain code
            $table->timestampTz('expires_at');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->unsignedSmallInteger('max_attempts');
            $table->timestampTz('consumed_at')->nullable();
            $table->timestampTz('invalidated_at')->nullable();
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestampsTz();

            $table->index(['voter_id', 'created_at']);
        });

        Schema::create('voting_sessions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('election_voter_id')->constrained('election_voters')->restrictOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->string('status', 20)->default(VotingSessionStatus::ACTIVE->value);
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestampTz('started_at');
            $table->timestampTz('last_activity_at');
            $table->timestampTz('expires_at');
            $table->timestampTz('ended_at')->nullable();
            $table->timestampsTz();

            $table->index(['status', 'expires_at']);
        });

        DB::statement('ALTER TABLE voting_sessions ADD CONSTRAINT voting_sessions_status_check CHECK ('.VotingSessionStatus::checkSql('status').')');
        // At most one active ballot session per voter per election.
        DB::statement("CREATE UNIQUE INDEX voting_sessions_one_active ON voting_sessions (election_voter_id) WHERE status = 'ACTIVE'");

        Schema::create('ballot_tokens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('election_id')->constrained('elections')->restrictOnDelete();
            $table->string('token_hash', 64)->unique();
            $table->boolean('is_consumed')->default(false);
        });

        Schema::create('ballots', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('election_id')->constrained('elections')->restrictOnDelete();
            $table->foreignUuid('ballot_token_id')->unique()->constrained('ballot_tokens')->restrictOnDelete();
            $table->string('reference', 40)->unique();

            $table->unique(['id', 'election_id']);
        });

        Schema::create('votes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('ballot_id');
            $table->uuid('election_id');
            $table->uuid('election_position_id');
            $table->uuid('candidate_id');

            $table->foreign(['ballot_id', 'election_id'])->references(['id', 'election_id'])->on('ballots')->restrictOnDelete();
            $table->foreign(['candidate_id', 'election_id'])->references(['id', 'election_id'])->on('candidates')->restrictOnDelete();
            $table->foreign(['candidate_id', 'election_position_id'])->references(['id', 'election_position_id'])->on('candidates')->restrictOnDelete();
            $table->foreign(['election_position_id', 'election_id'])->references(['id', 'election_id'])->on('election_positions')->restrictOnDelete();

            $table->unique(['ballot_id', 'candidate_id']);  // no candidate selected twice on one ballot
            $table->index(['election_id', 'election_position_id', 'candidate_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('votes');
        Schema::dropIfExists('ballots');
        Schema::dropIfExists('ballot_tokens');
        Schema::dropIfExists('voting_sessions');
        Schema::dropIfExists('otp_verifications');
    }
};
