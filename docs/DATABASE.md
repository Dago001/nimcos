# Database

PostgreSQL 16+. All primary keys are UUIDs, except `audit_logs.id`, a sequential key that defines the hash-chain order. All timestamps are `timestamptz` in UTC (the connection is pinned to UTC), displayed in Africa/Lagos.

The full ERD and rationale are in [ARCHITECTURE.md §3 to 4](ARCHITECTURE.md). This document is the operational reference.

## Tables

| Group | Table | Purpose |
|---|---|---|
| Access | `users`, `roles`, `permissions`, `role_permissions`, `user_roles` | Administrators and RBAC |
| Register | `voters` | Approved NIMCOS voter register (Service Number UNIQUE, not the PK) |
| | `voter_imports`, `voter_import_errors` | Import history, preview totals, rejected rows |
| Elections | `elections` | Election, schedule, lifecycle and result status |
| | `positions` | Catalogue of offices (14 seeded) |
| | `election_positions` | Positions on a specific ballot (seats, order, required) |
| | `candidates`, `candidate_documents` | Candidates per election position; uploaded files |
| | `election_voters` | Election-specific roll: ELIGIBLE / INELIGIBLE / SUSPENDED / VOTED |
| Identity side | `otp_verifications`, `voting_sessions` | Authentication and ballot sessions (know *who*) |
| Anonymous side | `ballot_tokens`, `ballots`, `votes` | One-time tokens and anonymous ballots (know *what*, never *who*) |
| Results | `result_tallies`, `tie_resolutions` | Computed tallies; recorded tie outcomes |
| Oversight | `audit_logs`, `security_alerts`, `system_settings` | Hash-chained log, alerts for review, runtime settings |
| Framework | `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`, `password_reset_tokens`, `migrations` | Laravel internals |

## Integrity constraints (enforced by PostgreSQL)

| Rule | Mechanism |
|---|---|
| Service Number unique and normalised | `UNIQUE(service_number)` + `CHECK (service_number = upper(btrim(service_number)))` |
| Status values valid | `CHECK (... IN (...))` on every status column, generated from the PHP enums |
| Voting window valid | `CHECK (ends_at > starts_at)` |
| Position belongs to election | `UNIQUE(election_id, position_id)` on `election_positions` |
| Candidate's position belongs to candidate's election | composite FK `candidates(election_position_id, election_id) → election_positions(id, election_id)` |
| One roll entry per voter per election | `UNIQUE(election_id, voter_id)` on `election_voters` |
| VOTED ⇔ voted_at set | `CHECK ((eligibility_status = 'VOTED') = (voted_at IS NOT NULL))` |
| One active ballot session per voter | partial unique index `voting_sessions(election_voter_id) WHERE status = 'ACTIVE'` |
| One ballot per token | `UNIQUE(ballots.ballot_token_id)`, `UNIQUE(ballot_tokens.token_hash)` |
| Vote belongs to ballot's election | composite FK `votes(ballot_id, election_id) → ballots(id, election_id)` |
| Vote's candidate is in that election and that position | composite FKs `votes(candidate_id, election_id)` and `votes(candidate_id, election_position_id) → candidates` |
| No candidate twice on one ballot | `UNIQUE(votes.ballot_id, candidate_id)` |
| Tie winner must be a candidate for that position | composite FK `tie_resolutions(winning_candidate_id, election_position_id)` |
| Published ⇒ election in published/archived state | `CHECK` on `elections` |

## Triggers (`2026_01_01_000700_create_integrity_triggers`)

| Trigger | Effect |
|---|---|
| `ballots_immutable`, `votes_immutable` | No UPDATE or DELETE, ever |
| `ballots_require_open`, `votes_require_open` | INSERT only while the election is `OPEN` |
| `audit_logs_immutable` | Audit log is append-only |
| `ballot_tokens_guard` | A consumed token cannot be un-consumed, altered or deleted |
| `election_voters_guard` | A VOTED record is final and cannot be deleted |
| `elections_status_guard` | Status may only follow the approved state machine; only DRAFT elections may be deleted |
| `result_tallies_frozen`, `tie_resolutions_frozen` | No change once results are published |

The triggers apply to the application role as well. A table owner can disable a trigger (`ALTER TABLE … DISABLE TRIGGER`). That is exactly what the audit hash chain and the results verification are designed to *detect*, and why production owner credentials must be tightly held (see SECURITY.md).

## Migrations

```bash
php artisan migrate            # apply
php artisan migrate:status     # inspect
```

Never run `migrate:fresh`, `migrate:rollback` or `db:wipe` against production. The migrations are written for PostgreSQL only.

## Useful read-only queries

```sql
-- Turnout per election (identity side only)
SELECT e.code, COUNT(*) FILTER (WHERE ev.eligibility_status IN ('ELIGIBLE','VOTED')) AS eligible,
       COUNT(*) FILTER (WHERE ev.eligibility_status = 'VOTED') AS voted
FROM elections e JOIN election_voters ev ON ev.election_id = e.id GROUP BY e.code;

-- Integrity invariant (must be equal for every election)
SELECT e.code,
       (SELECT COUNT(*) FROM ballots b WHERE b.election_id = e.id) AS ballots,
       (SELECT COUNT(*) FROM election_voters v WHERE v.election_id = e.id AND v.eligibility_status = 'VOTED') AS voted,
       (SELECT COUNT(*) FROM ballot_tokens t WHERE t.election_id = e.id AND t.is_consumed) AS tokens
FROM elections e;
```
