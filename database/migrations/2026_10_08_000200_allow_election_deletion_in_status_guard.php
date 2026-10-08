<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION nimcos_election_status_guard() RETURNS trigger AS $$
DECLARE
    ok boolean;
BEGIN
    IF TG_OP = 'DELETE' THEN
        RETURN OLD;
    END IF;
    IF NEW.status = OLD.status THEN
        RETURN NEW;
    END IF;
    ok := (OLD.status, NEW.status) IN (
        ('DRAFT','SCHEDULED'), ('SCHEDULED','DRAFT'), ('SCHEDULED','OPEN'),
        ('OPEN','CLOSED'), ('CLOSED','RESULTS_PUBLISHED'), ('RESULTS_PUBLISHED','ARCHIVED')
    );
    IF NOT ok THEN
        RAISE EXCEPTION 'NIMCOS integrity: election status change % -> % is not permitted', OLD.status, NEW.status
            USING ERRCODE = 'integrity_constraint_violation';
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;
SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION nimcos_election_status_guard() RETURNS trigger AS $$
DECLARE
    ok boolean;
BEGIN
    IF TG_OP = 'DELETE' THEN
        IF OLD.status <> 'DRAFT' THEN
            RAISE EXCEPTION 'NIMCOS integrity: only DRAFT elections can be deleted' USING ERRCODE = 'integrity_constraint_violation';
        END IF;
        RETURN OLD;
    END IF;
    IF NEW.status = OLD.status THEN
        RETURN NEW;
    END IF;
    ok := (OLD.status, NEW.status) IN (
        ('DRAFT','SCHEDULED'), ('SCHEDULED','DRAFT'), ('SCHEDULED','OPEN'),
        ('OPEN','CLOSED'), ('CLOSED','RESULTS_PUBLISHED'), ('RESULTS_PUBLISHED','ARCHIVED')
    );
    IF NOT ok THEN
        RAISE EXCEPTION 'NIMCOS integrity: election status change % -> % is not permitted', OLD.status, NEW.status
            USING ERRCODE = 'integrity_constraint_violation';
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;
SQL);
    }
};
