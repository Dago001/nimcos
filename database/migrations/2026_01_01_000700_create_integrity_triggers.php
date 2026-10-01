<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/*
 * Database-level integrity guarantees. These hold even if application code is
 * bypassed with direct SQL by the application role.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
-- 1. Ballots and votes are write-once, and may only be written while the election is OPEN.
CREATE OR REPLACE FUNCTION nimcos_block_modification() RETURNS trigger AS $$
BEGIN
    RAISE EXCEPTION 'NIMCOS integrity: % on % is not permitted', TG_OP, TG_TABLE_NAME
        USING ERRCODE = 'integrity_constraint_violation';
END;
$$ LANGUAGE plpgsql;

CREATE OR REPLACE FUNCTION nimcos_require_open_election() RETURNS trigger AS $$
DECLARE
    s text;
BEGIN
    SELECT status INTO s FROM elections WHERE id = NEW.election_id;
    IF s IS DISTINCT FROM 'OPEN' THEN
        RAISE EXCEPTION 'NIMCOS integrity: ballots can only be recorded while the election is OPEN (status %)', s
            USING ERRCODE = 'integrity_constraint_violation';
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER ballots_immutable BEFORE UPDATE OR DELETE ON ballots
    FOR EACH ROW EXECUTE FUNCTION nimcos_block_modification();
CREATE TRIGGER votes_immutable BEFORE UPDATE OR DELETE ON votes
    FOR EACH ROW EXECUTE FUNCTION nimcos_block_modification();
CREATE TRIGGER ballots_require_open BEFORE INSERT ON ballots
    FOR EACH ROW EXECUTE FUNCTION nimcos_require_open_election();
CREATE TRIGGER votes_require_open BEFORE INSERT ON votes
    FOR EACH ROW EXECUTE FUNCTION nimcos_require_open_election();

-- 2. Audit log is append-only.
CREATE TRIGGER audit_logs_immutable BEFORE UPDATE OR DELETE ON audit_logs
    FOR EACH ROW EXECUTE FUNCTION nimcos_block_modification();

-- 3. A consumed ballot token can never be un-consumed or deleted.
CREATE OR REPLACE FUNCTION nimcos_ballot_token_guard() RETURNS trigger AS $$
BEGIN
    IF TG_OP = 'DELETE' THEN
        RAISE EXCEPTION 'NIMCOS integrity: ballot tokens cannot be deleted' USING ERRCODE = 'integrity_constraint_violation';
    END IF;
    IF OLD.is_consumed AND NOT NEW.is_consumed THEN
        RAISE EXCEPTION 'NIMCOS integrity: consumed ballot token cannot be reused' USING ERRCODE = 'integrity_constraint_violation';
    END IF;
    IF NEW.token_hash <> OLD.token_hash OR NEW.election_id <> OLD.election_id THEN
        RAISE EXCEPTION 'NIMCOS integrity: ballot token identity is immutable' USING ERRCODE = 'integrity_constraint_violation';
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER ballot_tokens_guard BEFORE UPDATE OR DELETE ON ballot_tokens
    FOR EACH ROW EXECUTE FUNCTION nimcos_ballot_token_guard();

-- 4. Once a voter is marked VOTED for an election, that record is final.
CREATE OR REPLACE FUNCTION nimcos_election_voter_guard() RETURNS trigger AS $$
BEGIN
    IF TG_OP = 'DELETE' THEN
        IF OLD.eligibility_status = 'VOTED' THEN
            RAISE EXCEPTION 'NIMCOS integrity: a voter who has voted cannot be removed' USING ERRCODE = 'integrity_constraint_violation';
        END IF;
        RETURN OLD;
    END IF;
    IF OLD.eligibility_status = 'VOTED' AND (NEW.eligibility_status <> 'VOTED' OR NEW.voted_at IS DISTINCT FROM OLD.voted_at) THEN
        RAISE EXCEPTION 'NIMCOS integrity: VOTED status is final' USING ERRCODE = 'integrity_constraint_violation';
    END IF;
    IF NEW.election_id <> OLD.election_id OR NEW.voter_id <> OLD.voter_id THEN
        RAISE EXCEPTION 'NIMCOS integrity: election voter identity is immutable' USING ERRCODE = 'integrity_constraint_violation';
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER election_voters_guard BEFORE UPDATE OR DELETE ON election_voters
    FOR EACH ROW EXECUTE FUNCTION nimcos_election_voter_guard();

-- 5. Election status may only follow the approved state machine.
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

CREATE TRIGGER elections_status_guard BEFORE UPDATE OR DELETE ON elections
    FOR EACH ROW EXECUTE FUNCTION nimcos_election_status_guard();

-- 6. Result tallies and tie resolutions are frozen once results are published.
CREATE OR REPLACE FUNCTION nimcos_results_frozen_guard() RETURNS trigger AS $$
DECLARE
    rs text;
    eid uuid;
BEGIN
    eid := CASE WHEN TG_OP = 'DELETE' THEN OLD.election_id ELSE NEW.election_id END;
    SELECT result_status INTO rs FROM elections WHERE id = eid;
    IF rs = 'PUBLISHED' THEN
        RAISE EXCEPTION 'NIMCOS integrity: results are published and frozen' USING ERRCODE = 'integrity_constraint_violation';
    END IF;
    IF TG_OP = 'DELETE' THEN
        RETURN OLD;
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER result_tallies_frozen BEFORE INSERT OR UPDATE OR DELETE ON result_tallies
    FOR EACH ROW EXECUTE FUNCTION nimcos_results_frozen_guard();
CREATE TRIGGER tie_resolutions_frozen BEFORE INSERT OR UPDATE OR DELETE ON tie_resolutions
    FOR EACH ROW EXECUTE FUNCTION nimcos_results_frozen_guard();
SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared(<<<'SQL'
DROP TRIGGER IF EXISTS tie_resolutions_frozen ON tie_resolutions;
DROP TRIGGER IF EXISTS result_tallies_frozen ON result_tallies;
DROP TRIGGER IF EXISTS elections_status_guard ON elections;
DROP TRIGGER IF EXISTS election_voters_guard ON election_voters;
DROP TRIGGER IF EXISTS ballot_tokens_guard ON ballot_tokens;
DROP TRIGGER IF EXISTS audit_logs_immutable ON audit_logs;
DROP TRIGGER IF EXISTS votes_require_open ON votes;
DROP TRIGGER IF EXISTS ballots_require_open ON ballots;
DROP TRIGGER IF EXISTS votes_immutable ON votes;
DROP TRIGGER IF EXISTS ballots_immutable ON ballots;
DROP FUNCTION IF EXISTS nimcos_results_frozen_guard();
DROP FUNCTION IF EXISTS nimcos_election_status_guard();
DROP FUNCTION IF EXISTS nimcos_election_voter_guard();
DROP FUNCTION IF EXISTS nimcos_ballot_token_guard();
DROP FUNCTION IF EXISTS nimcos_require_open_election();
DROP FUNCTION IF EXISTS nimcos_block_modification();
SQL);
    }
};
