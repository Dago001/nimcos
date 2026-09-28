<?php

use App\Enums\AccountStatus;
use App\Enums\ImportStatus;
use App\Enums\MembershipStatus;
use App\Enums\VerificationStatus;
use App\Enums\VoterEligibility;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('voters', function (Blueprint $table) {
            $table->uuid('id')->primary();                  // internal ID, never the Service Number
            $table->string('service_number', 20)->unique(); // stored trimmed + upper-cased
            $table->string('surname', 100)->index();
            $table->string('first_name', 100);
            $table->string('other_names', 150)->nullable();
            $table->string('rank', 80)->nullable()->index();
            $table->string('command', 120)->nullable()->index();
            $table->string('formation', 120)->nullable()->index();
            $table->string('phone', 20)->nullable();        // E.164, e.g. +2348031234567
            $table->string('email', 191)->nullable();
            $table->string('membership_status', 20)->default(MembershipStatus::ACTIVE->value);
            $table->string('eligibility_status', 20)->default(VoterEligibility::ELIGIBLE->value);
            $table->string('verification_status', 20)->default(VerificationStatus::UNVERIFIED->value);
            $table->string('account_status', 20)->default(AccountStatus::ACTIVE->value);
            $table->text('status_reason')->nullable();
            $table->timestampTz('registered_at')->useCurrent();
            $table->timestampTz('verified_at')->nullable();
            $table->foreignUuid('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_test_data')->default(false);
            $table->timestampsTz();
        });

        DB::statement('ALTER TABLE voters ADD CONSTRAINT voters_membership_check CHECK ('.MembershipStatus::checkSql('membership_status').')');
        DB::statement('ALTER TABLE voters ADD CONSTRAINT voters_eligibility_check CHECK ('.VoterEligibility::checkSql('eligibility_status').')');
        DB::statement('ALTER TABLE voters ADD CONSTRAINT voters_verification_check CHECK ('.VerificationStatus::checkSql('verification_status').')');
        DB::statement('ALTER TABLE voters ADD CONSTRAINT voters_account_check CHECK ('.AccountStatus::checkSql('account_status').')');
        DB::statement('ALTER TABLE voters ADD CONSTRAINT voters_service_number_normalised CHECK (service_number = upper(btrim(service_number)) AND length(service_number) >= 3)');

        Schema::create('voter_imports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->string('original_filename', 255);
            $table->string('stored_path', 255);
            $table->string('file_hash', 64);
            $table->string('status', 20)->default(ImportStatus::PREVIEWED->value);
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('valid_rows')->default(0);
            $table->unsignedInteger('imported_count')->default(0);
            $table->unsignedInteger('updated_count')->default(0);
            $table->unsignedInteger('unchanged_count')->default(0);
            $table->unsignedInteger('duplicate_count')->default(0);
            $table->unsignedInteger('invalid_count')->default(0);
            $table->unsignedInteger('rejected_count')->default(0);
            $table->boolean('update_existing')->default(true);
            $table->foreignUuid('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('confirmed_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->text('failure_reason')->nullable();
            $table->timestampsTz();
        });

        DB::statement('ALTER TABLE voter_imports ADD CONSTRAINT voter_imports_status_check CHECK ('.ImportStatus::checkSql('status').')');

        Schema::create('voter_import_errors', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('voter_import_id')->constrained('voter_imports')->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->string('service_number', 50)->nullable();
            $table->string('error_type', 20);               // INVALID, DUPLICATE, REJECTED
            $table->json('messages');
            $table->json('raw');
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['voter_import_id', 'row_number']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('voter_import_errors');
        Schema::dropIfExists('voter_imports');
        Schema::dropIfExists('voters');
    }
};
