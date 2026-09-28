<?php

use App\Enums\AlertSeverity;
use App\Enums\AlertStatus;
use App\Enums\AuditResult;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Append-only; each row carries a SHA-256 hash chained to the previous row.
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();                                   // sequential: defines chain order
            $table->string('actor_type', 20);               // ADMIN, VOTER, SYSTEM, ANONYMOUS
            $table->uuid('actor_id')->nullable();
            $table->string('actor_label', 191)->nullable();
            $table->string('action', 80);
            $table->string('entity_type', 60)->nullable();
            $table->string('entity_id', 64)->nullable();
            $table->string('result', 10);
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->jsonb('metadata')->nullable();
            $table->timestampTz('created_at', 6);
            $table->string('prev_hash', 64);
            $table->string('hash', 64)->unique();

            $table->index('created_at');
            $table->index(['actor_type', 'actor_id']);
            $table->index('action');
            $table->index(['entity_type', 'entity_id']);
            $table->index('ip');
        });

        DB::statement('ALTER TABLE audit_logs ADD CONSTRAINT audit_logs_result_check CHECK ('.AuditResult::checkSql('result').')');

        Schema::create('security_alerts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type', 60);
            $table->string('severity', 10);
            $table->string('fingerprint', 64);              // dedupe key within the open window
            $table->string('ip', 45)->nullable();
            $table->string('subject', 191)->nullable();     // e.g. masked service number / admin email
            $table->text('description');
            $table->jsonb('metadata')->nullable();
            $table->unsignedInteger('occurrences')->default(1);
            $table->timestampTz('first_seen_at');
            $table->timestampTz('last_seen_at');
            $table->string('status', 12)->default(AlertStatus::OPEN->value);
            $table->foreignUuid('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->timestampsTz();

            $table->index(['status', 'severity']);
            $table->index(['fingerprint', 'status']);
        });

        DB::statement('ALTER TABLE security_alerts ADD CONSTRAINT security_alerts_status_check CHECK ('.AlertStatus::checkSql('status').')');
        DB::statement('ALTER TABLE security_alerts ADD CONSTRAINT security_alerts_severity_check CHECK ('.AlertSeverity::checkSql('severity').')');

        Schema::create('system_settings', function (Blueprint $table) {
            $table->string('key', 80)->primary();
            $table->jsonb('value');
            $table->foreignUuid('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
        Schema::dropIfExists('security_alerts');
        Schema::dropIfExists('audit_logs');
    }
};
