<?php

use App\Enums\CandidateStatus;
use App\Enums\ElectionStatus;
use App\Enums\ElectionType;
use App\Enums\EligibilityStatus;
use App\Enums\RecordStatus;
use App\Enums\ResultStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('elections', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 200);
            $table->string('code', 40)->unique();            // e.g. NIMCOS-2026-EC (used in voter URLs)
            $table->text('description')->nullable();
            $table->string('election_type', 30)->default(ElectionType::GENERAL->value);
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');
            $table->string('status', 30)->default(ElectionStatus::DRAFT->value)->index();
            $table->string('result_status', 30)->default(ResultStatus::NOT_CALCULATED->value);
            $table->boolean('auto_open')->default(true);
            $table->boolean('auto_close')->default(true);
            $table->boolean('interim_results_enabled')->default(false);
            $table->string('receipt_year', 4);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('scheduled_at')->nullable();
            $table->foreignUuid('scheduled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('opened_at')->nullable();
            $table->foreignUuid('opened_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('closed_at')->nullable();
            $table->foreignUuid('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('published_at')->nullable();
            $table->foreignUuid('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('archived_at')->nullable();
            $table->boolean('is_test_data')->default(false);
            $table->timestampsTz();
        });

        DB::statement('ALTER TABLE elections ADD CONSTRAINT elections_status_check CHECK ('.ElectionStatus::checkSql('status').')');
        DB::statement('ALTER TABLE elections ADD CONSTRAINT elections_result_status_check CHECK ('.ResultStatus::checkSql('result_status').')');
        DB::statement('ALTER TABLE elections ADD CONSTRAINT elections_type_check CHECK ('.ElectionType::checkSql('election_type').')');
        DB::statement('ALTER TABLE elections ADD CONSTRAINT elections_window_check CHECK (ends_at > starts_at)');
        DB::statement("ALTER TABLE elections ADD CONSTRAINT elections_publish_check CHECK (result_status <> 'PUBLISHED' OR status IN ('RESULTS_PUBLISHED','ARCHIVED'))");

        // Catalogue of offices; per-election configuration lives in election_positions.
        Schema::create('positions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name', 120)->unique();
            $table->text('description')->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->unsignedSmallInteger('default_seats')->default(1);
            $table->string('status', 20)->default(RecordStatus::ACTIVE->value);
            $table->timestampsTz();
        });

        DB::statement('ALTER TABLE positions ADD CONSTRAINT positions_status_check CHECK ('.RecordStatus::checkSql('status').')');
        DB::statement('ALTER TABLE positions ADD CONSTRAINT positions_seats_check CHECK (default_seats >= 1)');

        Schema::create('election_positions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('election_id')->constrained('elections')->cascadeOnDelete();
            $table->foreignUuid('position_id')->constrained('positions')->restrictOnDelete();
            $table->unsignedSmallInteger('seats')->default(1);
            $table->unsignedInteger('display_order')->default(0);
            $table->boolean('is_required')->default(true);
            $table->timestampsTz();

            $table->unique(['election_id', 'position_id']);
            $table->unique(['id', 'election_id']);  // target for composite FKs
        });

        DB::statement('ALTER TABLE election_positions ADD CONSTRAINT election_positions_seats_check CHECK (seats >= 1)');

        Schema::create('candidates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('election_id');
            $table->uuid('election_position_id');
            $table->unsignedInteger('candidate_number');
            $table->string('surname', 100);
            $table->string('first_name', 100);
            $table->string('other_names', 150)->nullable();
            $table->string('service_number', 20)->nullable();
            $table->string('rank', 80)->nullable();
            $table->string('command', 120)->nullable();
            $table->text('biography')->nullable();
            $table->string('photo_path', 255)->nullable();
            $table->string('status', 20)->default(CandidateStatus::ACTIVE->value);
            $table->unsignedInteger('display_order')->default(0);
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_test_data')->default(false);
            $table->timestampsTz();

            $table->foreign('election_id')->references('id')->on('elections')->cascadeOnDelete();
            // A candidate's position must belong to the candidate's election.
            $table->foreign(['election_position_id', 'election_id'])
                ->references(['id', 'election_id'])->on('election_positions')->restrictOnDelete();

            $table->unique(['election_id', 'candidate_number']);
            $table->unique(['id', 'election_id']);
            $table->unique(['id', 'election_position_id']);
            $table->index(['election_position_id', 'status']);
        });

        DB::statement('ALTER TABLE candidates ADD CONSTRAINT candidates_status_check CHECK ('.CandidateStatus::checkSql('status').')');

        Schema::create('candidate_documents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('candidate_id')->constrained('candidates')->cascadeOnDelete();
            $table->string('type', 40);                 // PHOTO, MANIFESTO, NOMINATION_FORM ...
            $table->string('original_name', 255);
            $table->string('stored_path', 255);
            $table->string('mime', 100);
            $table->unsignedInteger('size');
            $table->string('sha256', 64);
            $table->foreignUuid('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
        });

        Schema::create('election_voters', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('election_id')->constrained('elections')->restrictOnDelete();
            $table->foreignUuid('voter_id')->constrained('voters')->restrictOnDelete();
            $table->string('eligibility_status', 20)->default(EligibilityStatus::ELIGIBLE->value);
            $table->text('eligibility_reason')->nullable();
            $table->timestampTz('authorized_at')->nullable();
            $table->foreignUuid('authorized_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('voted_at')->nullable();
            $table->timestampsTz();

            $table->unique(['election_id', 'voter_id']);   // one eligibility record (and one vote) per election
            $table->index(['election_id', 'eligibility_status']);
            $table->index(['election_id', 'voted_at']);
        });

        DB::statement('ALTER TABLE election_voters ADD CONSTRAINT election_voters_status_check CHECK ('.EligibilityStatus::checkSql('eligibility_status').')');
        DB::statement("ALTER TABLE election_voters ADD CONSTRAINT election_voters_voted_check CHECK ((eligibility_status = 'VOTED') = (voted_at IS NOT NULL))");
    }

    public function down(): void
    {
        Schema::dropIfExists('election_voters');
        Schema::dropIfExists('candidate_documents');
        Schema::dropIfExists('candidates');
        Schema::dropIfExists('election_positions');
        Schema::dropIfExists('positions');
        Schema::dropIfExists('elections');
    }
};
