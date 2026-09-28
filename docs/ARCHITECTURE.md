# NIMCOS E-VOTING: System Architecture (Phase 1)

Nigeria Immigration Multi-Purpose Cooperative Society Electronic Voting Platform.

This document is the design baseline. Every later phase implements what is described here; where the code deviates, this document is updated in the same change.

---

## 1. System architecture

```
                        ┌──────────────────────────── HTTPS (TLS 1.2+) ───────────────────────────┐
   Voter (phone/PC) ───►│                                                                         │
   Admin (PC/tablet) ──►│  Nginx  ──►  PHP-FPM 8.3+  ──►  Laravel application (monolith)           │
                        │                               │                                         │
                        │                               ├── Voter module   (/ , /otp, /ballot …)  │
                        │                               ├── Admin module   (/admin/…)             │
                        │                               ├── Public module  (/results, /receipt)   │
                        │                               └── JSON endpoints (/admin/…/stats)       │
                        │                                                                         │
                        │  Queue worker (database queue) ── OTP and admin emails, voter-import commits    │
                        │  Scheduler (every minute) ─────── auto open/close, session expiry,       │
                        │                                    security-event detection              │
                        └──────────────────────────────┬──────────────────────────────────────────┘
                                                       │
                                     PostgreSQL 16+ (single primary, WAL archiving)
                                     sessions · cache · jobs · all election data
```

**Why a monolith:** one deployable unit, one database, one set of transactions. An internal team can operate it without a separate SPA, API gateway or message broker. Vote integrity depends on database transactions, so all state lives in PostgreSQL. Sessions, cache (rate limiter), locks and queues use the database too, which means no Redis is required. Redis can be added later for scale without code changes.

**Layers inside the application**

| Layer | Responsibility | Location |
|---|---|---|
| Routes + middleware | Authentication, permission gates, rate limits, security headers | `routes/`, `app/Http/Middleware` |
| Controllers | Thin: validate (Form Request) → call service → render | `app/Http/Controllers` |
| Form Requests | Input validation and normalisation | `app/Http/Requests` |
| Services / Actions | All business rules (voting, OTP, results, import, transitions) | `app/Services` |
| Models | Relationships, casts, guarded attributes (no mass-assignment of status fields) | `app/Models` |
| Enums | Status vocabularies shared by code and DB check constraints | `app/Enums` |
| Jobs / Notifications | Email OTP and admin notice delivery (encrypted payloads), import processing | `app/Jobs` |
| DB triggers | Immutability of ballots, votes and audit logs; enforced even against direct SQL by the app role | migrations |

---

## 2. Technology stack

| Concern | Choice | Reason |
|---|---|---|
| Language/framework | PHP 8.3+ / Laravel 13 | Requested; mature, well-known, secure defaults |
| Database | PostgreSQL 16+ | Row locks, composite FKs, CHECK constraints, triggers, `timestamptz` |
| Frontend | Blade + vanilla JS + hand-written CSS in `public/assets` | No build step needed to deploy; strict CSP (no inline JS/CSS) |
| Charts | Small in-house SVG renderer | No third-party JS on election pages |
| Spreadsheet import/export | `phpoffice/phpspreadsheet` | CSV + XLSX |
| PDF reports | `barryvdh/laravel-dompdf` | Server-side PDF without external services |
| MFA | TOTP (RFC 6238), implemented in-house + `bacon/bacon-qr-code` for enrolment QR | Works with any authenticator app |
| Password hashing | Argon2id | Spec §26 |
| Queue / cache / sessions | Laravel `database` drivers | Single datastore |
| Tests | PHPUnit (Laravel test runner) against real PostgreSQL | Locks/constraints tested for real |

---

## 3. Database ERD

```
roles ─┬─< role_permissions >─┬─ permissions
       └─< user_roles >── users ───────────────┐ (created_by / actor)
                                               │
voters ──< election_voters >── elections ──< election_positions >── positions
  │               │                │                 │
  │               │                │                 └─< candidates ──< candidate_documents
  │               │                │                          │
  │               └─< voting_sessions                         │
  │                                │                          │
  └─< otp_verifications            ├─< ballot_tokens ─1:1─ ballots ──< votes >──┘
                                   │     (NO link to voter)          (NO link to voter)
voter_imports ──< voter_import_errors
                                   ├─< result_tallies           (computed, never hand-edited)
                                   └─< tie_resolutions
audit_logs (append-only, hash-chained)     security_alerts     system_settings
sessions · cache · cache_locks · jobs · failed_jobs  (framework)
```

The secrecy boundary is the absence of any foreign key or shared identifier between `{voters, election_voters, voting_sessions, otp_verifications}` and `{ballot_tokens, ballots, votes}`.

---

## 4. Tables and relationships

All primary keys are UUIDs. Admin-facing tables use time-ordered UUIDs. `ballot_tokens`, `ballots` and `votes` use **random v4 UUIDs**, so key order does not reveal cast order. All timestamps are `timestamptz`, stored in UTC and displayed in `Africa/Lagos`.

### Identity & access
| Table | Key columns | Constraints |
|---|---|---|
| `users` | name, email, password (argon2id), status, mfa_secret (encrypted), mfa_confirmed_at, failed_login_count, locked_until, last_login_at/ip, password_changed_at | email UNIQUE; status CHECK |
| `roles` | name (SUPER_ADMIN…), label, description | name UNIQUE |
| `permissions` | name (`manage_voters`…), label, group | name UNIQUE |
| `role_permissions` | role_id, permission_id | PK(role_id, permission_id), FKs cascade |
| `user_roles` | user_id, role_id, assigned_by | PK(user_id, role_id) |

### Voter register
| Table | Key columns | Constraints |
|---|---|---|
| `voters` | service_number, surname, first_name, other_names, rank, command, formation, phone (E.164), email, membership_status, eligibility_status, verification_status, account_status, registered_at, verified_at, verified_by, created_by, updated_by, is_test_data | **service_number UNIQUE** (upper-cased, trimmed); status CHECKs; indexes on surname, rank, command, formation |
| `voter_imports` | uploaded_by, original_filename, stored_path, file_hash, status (PREVIEWED/PROCESSING/COMPLETED/FAILED/CANCELLED), totals (total, imported, updated, unchanged, duplicate, invalid, rejected), confirmed_by/at, completed_at | status CHECK |
| `voter_import_errors` | voter_import_id, row_number, service_number, error_type (INVALID/DUPLICATE), messages (json), raw (json) | FK cascade |

### Elections
| Table | Key columns | Constraints |
|---|---|---|
| `elections` | name, code, description, election_type, starts_at, ends_at, status, result_status, auto_open, auto_close, interim_results_enabled, created_by, scheduled_at, opened_at/by, closed_at/by, published_at/by, archived_at, is_test_data | code UNIQUE; `ends_at > starts_at`; status/result_status CHECK |
| `positions` | name, description, display_order, default_seats, status | name UNIQUE; seats ≥ 1 |
| `election_positions` | election_id, position_id, seats, display_order, is_required | UNIQUE(election_id, position_id); **UNIQUE(id, election_id)** (target of composite FKs) |
| `candidates` | election_id, election_position_id, candidate_number, surname, first_name, other_names, service_number, rank, command, biography, photo_path, status, display_order | **FK(election_position_id, election_id) → election_positions(id, election_id)**; UNIQUE(election_id, candidate_number); UNIQUE(id, election_id); UNIQUE(id, election_position_id) |
| `candidate_documents` | candidate_id, type, original_name, stored_path, mime, size, sha256, uploaded_by | FK cascade |
| `election_voters` | election_id, voter_id, eligibility_status (ELIGIBLE/INELIGIBLE/SUSPENDED/VOTED), eligibility_reason, authorized_at, authorized_by, voted_at | **UNIQUE(election_id, voter_id)**; CHECK voted_at present ⇔ status VOTED |

### Authentication & voting
| Table | Key columns | Constraints |
|---|---|---|
| `otp_verifications` | voter_id, channel, destination_masked, code_hash (HMAC-SHA256 keyed by APP_KEY), expires_at, attempts, max_attempts, consumed_at, invalidated_at, ip, user_agent | partial index on active OTP per voter |
| `voting_sessions` | election_voter_id, token_hash, status (ACTIVE/COMPLETED/EXPIRED/REVOKED), ip, user_agent, started_at, last_activity_at, expires_at, ended_at | token_hash UNIQUE; **partial UNIQUE(election_voter_id) WHERE status='ACTIVE'** |
| `ballot_tokens` | election_id, token_hash, consumed_at (date precision not stored; boolean `is_consumed`) | token_hash UNIQUE; **no voter / session / timestamp columns** |
| `ballots` | election_id, ballot_token_id, reference (NIM-2026-XXXXXXXX) | ballot_token_id UNIQUE (one ballot per token); reference UNIQUE; UNIQUE(id, election_id); **no timestamps** |
| `votes` | ballot_id, election_id, election_position_id, candidate_id | FK(ballot_id, election_id) → ballots; FK(candidate_id, election_id) → candidates; FK(candidate_id, election_position_id) → candidates; FK(election_position_id, election_id) → election_positions; UNIQUE(ballot_id, candidate_id) |

### Results, audit, operations
| Table | Key columns | Notes |
|---|---|---|
| `result_tallies` | election_id, election_position_id, candidate_id, votes, rank, is_tied, calculation_hash, calculated_at/by | Written only by `ResultsCalculator`; trigger blocks changes once published |
| `tie_resolutions` | election_id, election_position_id, method (RUNOFF_PENDING/DRAW_OF_LOTS/COMMITTEE_DECISION/RUNOFF_HELD), winning_candidate_id (nullable), notes, resolved_by, resolved_at | Declares an outcome without altering counts |
| `audit_logs` | actor_type, actor_id, actor_label, action, entity_type, entity_id, result, ip, user_agent, metadata (json), created_at, prev_hash, hash | **UPDATE/DELETE blocked by trigger**; SHA-256 hash chain, verifiable with `php artisan audit:verify` |
| `security_alerts` | type, severity, ip, subject, description, metadata, occurrences, status (OPEN/REVIEWED/DISMISSED), reviewed_by/at, review_notes | Flags for human review; never auto-accuses |
| `system_settings` | key, value (json), updated_by | key UNIQUE |

Immutability triggers:
- `ballots`, `votes`: no UPDATE, no DELETE, and no INSERT unless the parent election is `OPEN`.
- `audit_logs`: no UPDATE, no DELETE.
- `result_tallies`: no INSERT/UPDATE/DELETE once `elections.result_status = 'PUBLISHED'`.
- `election_voters`: once a row is `VOTED`, it cannot change back.

---

## 5. Authentication architecture

### Voters (guard `voter`)
```
Service Number ─► normalise (trim, upper) ─► rate limit (IP + service number)
   ─► voter exists AND account ACTIVE AND ≥1 OPEN election where election_voters status ∈ {ELIGIBLE, VOTED}
        └─ otherwise: one generic refusal message (no distinction between unknown / ineligible) + audit + counter
   ─► invalidate previous active OTPs ─► generate 6-digit OTP with random_int()
   ─► store HMAC-SHA256(code) only, expires in 5 min, max 5 attempts
   ─► queue encrypted SendVoterOtp job (email) ─► session holds only the OTP record id
OTP entry ─► rate limit ─► constant-time compare ─► attempts++ ─► on success mark consumed
   ─► session()->regenerate() (anti-fixation) ─► Auth::guard('voter')->login()
   ─► VOTED status revealed only now (so nobody can learn a colleague's turnout by typing a number)
```
Resend: allowed after 60 s, max 3 per 15 min per voter; each resend invalidates the earlier code.

### Administrators (guard `web`)
Email + password (Argon2id). Accounts lock for 15 minutes after 5 failures, plus an IP rate limit. TOTP MFA can be enabled per user and enforced system-wide by the `require_admin_mfa` setting, which is recommended in production. Sessions are regenerated on login, and the idle timeout is 30 minutes. **Re-authentication:** opening or closing an election, calculating or publishing results, creating admins and changing roles or settings all require the current password, plus the current TOTP code if MFA is enabled. The check is repeated for each action inside the confirmation form, so there is no "sudo window" that can be abused.

### Session hardening
Session cookie settings: `Secure` (production), `HttpOnly`, `SameSite=Lax`, encrypted payload, database driver, CSRF on every state-changing form, and regeneration on privilege change.

---

## 6. Voting architecture

```
Authenticated voter ─► choose election (auto if one) ─► intro page ─► [START VOTING]
  StartVotingSession (transaction):
     lock election_voters row; require ELIGIBLE and election open (status OPEN and now in [starts_at, ends_at))
     revoke any other ACTIVE voting session for this election_voter (+ security alert "multiple sessions")
     create voting_session (token hash)             ─► raw session token kept in the Laravel session
     create ballot_token (random 256-bit, hash only) ─► raw ballot token sent ONLY as an encrypted
                                                        HttpOnly cookie scoped to /ballot; never persisted
                                                        server-side alongside the voter
Ballot page (all positions; JS turns it into step-by-step "Position n of 14" on phones; works without JS)
  ─► POST review: server validates selections, renders review page with selections as hidden fields
                  (selections are never stored server-side before submission)
  ─► [BACK AND EDIT] re-posts to ballot with selections prefilled
  ─► [SUBMIT FINAL BALLOT] ─► confirm modal ─► POST submit
SubmitBallot (single transaction, SERIALIZABLE not required: explicit row locks):
     1  SELECT election FOR SHARE; must be OPEN and now < ends_at
     2  SELECT voting_session FOR UPDATE; ACTIVE, not expired, belongs to this voter
     3  SELECT election_voter FOR UPDATE
          ├─ VOTED  → idempotent replay: find ballot via ballot-token hash → return same receipt
          └─ not ELIGIBLE → refuse
     4  validate every selection server-side (candidate ACTIVE, in this election, in this position,
        required positions answered, ≤ seats, no duplicates)
     5  SELECT ballot_token FOR UPDATE by hash; must be unconsumed and for this election
     6  UPDATE election_voters SET status=VOTED, voted_at=now WHERE id=? AND status='ELIGIBLE' (must affect 1 row)
     7  UPDATE ballot_tokens SET is_consumed=true
     8  INSERT ballot (random UUID, reference) ; INSERT votes
     9  UPDATE voting_session SET status=COMPLETED
     COMMIT   (any exception → ROLLBACK, voter stays ELIGIBLE)
  ─► audit "VOTE_CAST" (voter + election only, never ballot id/reference/selections)
  ─► receipt page: reference (from ballot via cookie token) + time (from election_voters.voted_at)
```

**Defences against double voting:** (a) an application check, (b) a row lock on `election_voters`, (c) a compare-and-set update that must affect exactly one row, (d) `ballot_tokens.token_hash` / `ballots.ballot_token_id` UNIQUE, (e) the `VOTED` state cannot be reverted (trigger), (f) results verification asserts `count(ballots) = count(election_voters WHERE VOTED)`.

**Idempotency:** the ballot token acts as the idempotency key. If the browser retries after a timeout, the request carries the same token cookie. The server sees the voter already `VOTED`, finds the ballot through the consumed token and returns the original receipt. No second ballot can exist, because a token maps to at most one ballot (UNIQUE).

**Time:** the server clock (UTC, NTP-synchronised) is authoritative. Voting is refused once `now ≥ ends_at`, even if the scheduler has not yet flipped the status to CLOSED.

---

## 7. Ballot secrecy architecture

| Data | Knows voter identity | Knows selections |
|---|---|---|
| `election_voters`, `voting_sessions`, `otp_verifications`, `audit_logs` | yes | **no** |
| `ballot_tokens`, `ballots`, `votes`, `result_tallies` | **no** | yes |
| Laravel session (DB, encrypted) | yes | no (selections are never stored in it) |
| Ballot-token cookie (in the voter's browser only) | none | links the voter's browser to *their own* ballot until logout |

Measures:
1. There is no FK, column or log entry that joins the two sides.
2. Ballot and vote rows have random v4 UUIDs and **no timestamps**, so row keys or time columns cannot be ordered and matched against `voted_at`.
3. The raw ballot token never touches the server's storage. Only its SHA-256 hash is stored in `ballot_tokens`, and the voter side never stores the token or its hash.
4. The audit log records "voter X cast a ballot in election Y" and never the reference or the choices.
5. Turnout statistics come from `election_voters` only. The dashboard live vote count is an aggregate `COUNT(*)` per candidate from `votes` (shown while voting is open unless switched off per election, and only to `view_results` holders); it never exposes a ballot or a voter.
6. The receipt shows a reference and a time only, so it cannot prove to anyone how the voter voted. Public receipt verification says only "this reference is recorded in election X".
7. The admin UI has no view that lists individual ballots.

Residual risk, documented in `SECURITY.md`: a database superuser with access to PostgreSQL WAL/physical row order and precise request logs could attempt timing correlation. This is mitigated by organisational controls: restricted DB access, no statement logging of `ballots`/`votes`, and log retention policy.

---

## 8. Role & permission matrix

| Permission | Super Admin | Election Admin | Returning Officer | Auditor |
|---|:-:|:-:|:-:|:-:|
| view_dashboard | ✔ | ✔ | ✔ | ✔ |
| manage_voters | ✔ | ✔ | | |
| import_voters | ✔ | ✔ | | |
| verify_voters | ✔ | ✔ | | |
| manage_elections | ✔ | ✔ | | |
| manage_positions | ✔ | ✔ | | |
| manage_candidates | ✔ | ✔ | | |
| open_election | ✔ | ✔ | ✔ | |
| close_election | ✔ | ✔ | ✔ | |
| view_live_statistics | ✔ | ✔ | ✔ | ✔ |
| view_results | ✔ | ✔ | ✔ | ✔ |
| publish_results (calculate, verify, resolve ties, publish) | | | ✔ | |
| generate_reports | ✔ | ✔ | ✔ | ✔ |
| view_audit_logs | ✔ | | ✔ | ✔ |
| manage_admins | ✔ | | | |
| manage_system_settings | ✔ | | | |

Code checks **permissions** (`can:publish_results`), never role names. The matrix is seeded and can be edited by holders of `manage_admins`, and every change is audited. `publish_results` is deliberately withheld from Super Admin to separate duties. Voters are a separate guard with no admin permissions.

---

## 9. Application route map (web)

| Area | Routes |
|---|---|
| Voter | `GET /` entry · `POST /access` · `GET/POST /verify` (OTP) · `POST /verify/resend` · `GET /elections` · `GET /elections/{code}` intro · `POST /elections/{code}/start` · `GET /ballot` · `POST /ballot/review` · `POST /ballot/edit` · `POST /ballot/submit` · `GET /ballot/receipt` · `GET /already-voted` · `POST /logout` |
| Public | `GET /results` · `GET /results/{code}` (published only) · `GET/POST /receipt/verify` · `GET /media/candidates/{candidate}/photo` |
| Admin auth | `GET/POST /admin/login` · `GET/POST /admin/mfa` · `POST /admin/logout` · `GET/POST /admin/profile/mfa` · `GET/POST /admin/profile/password` |
| Dashboard | `GET /admin` |
| Elections | `/admin/elections` resource · `/admin/elections/{e}/positions` · `/admin/elections/{e}/candidates` · `POST /admin/elections/{e}/schedule`, `/unschedule`, `/open`, `/close`, `/archive` |
| Positions | `/admin/positions` resource (catalogue) |
| Candidates | `/admin/candidates` (search across elections) · create/edit/activate/deactivate |
| Voters | `/admin/voters` resource · `/admin/voters/suspended` · `POST /admin/voters/{v}/verify`, `/suspend`, `/reinstate` · `/admin/imports` (upload, preview, confirm, errors download) |
| Eligibility | `/admin/elections/{e}/eligibility` (list/filter, bulk authorise, suspend, reinstate) |
| Monitoring | `GET /admin/elections/{e}/monitor` · `GET /admin/elections/{e}/monitor/stats` (JSON) |
| Results | `/admin/elections/{e}/results` · `POST …/calculate` · `POST …/verify` · `POST …/ties` · `POST …/publish` |
| Reports | `/admin/reports` · `GET /admin/reports/{type}.{csv|xlsx|pdf}` |
| Audit | `/admin/audit` · `/admin/security-alerts` |
| Administration | `/admin/users` · `/admin/roles` · `/admin/settings` |

## 10. API endpoint map (JSON)

The UI is server-rendered. JSON is exposed only where the browser needs live data, and every endpoint sits behind the same session, CSRF and permission middleware:

| Method | Path | Permission | Purpose |
|---|---|---|---|
| GET | `/admin/elections/{e}/monitor/stats` | view_live_statistics | Turnout, sessions, hourly activity, alerts |
| GET | `/admin/dashboard/stats` | view_dashboard | Headline numbers for the dashboard |

The mapping from the specification's example REST endpoints to the implemented routes is in `docs/API.md`.

## 11. UI page map
- **Voter** (no navigation chrome): Entry → OTP → Election chooser/intro → Ballot (step-by-step on mobile) → Review → Confirm modal → Receipt. Also: Already voted, Session expired, No open election.
- **Public:** Published results, Receipt verification.
- **Admin:** a sidebar layout (collapses on tablet/mobile) with the sections of spec §33.

## 12. Election workflow (state machine)
```
DRAFT ──schedule──► SCHEDULED ──open (manual/auto at starts_at)──► OPEN ──close (manual/auto at ends_at)──► CLOSED
  ▲                    │                                                                                       │
  └────unschedule──────┘                                                          calculate → verify → publish │
                                                                                                               ▼
                                                                     ARCHIVED ◄──archive── RESULTS_PUBLISHED
```
- **Schedule** requires at least one position, at least one active candidate per position, at least one eligible voter, and `ends_at > starts_at > now`.
- **Structure lock:** positions, candidates and times are editable only in DRAFT. Eligibility is editable in DRAFT/SCHEDULED, and while OPEN the only allowed change is suspension.
- **CLOSED is terminal** for voting. The normal UI has no "reopen".
- **Result status:** `NOT_CALCULATED → CALCULATED → VERIFIED → PUBLISHED`. Recalculation resets the status to CALCULATED. Publishing requires VERIFIED status and a resolution for every tie.

## 13. Voter workflow
Service Number → OTP → (election) → intro → ballot → review → confirm → receipt → finish (logout, cookie cleared).

## 14. Administrator workflow
1. Election Admin imports the register (preview → confirm), and admins verify voters.
2. Election Admin creates the election, attaches the 14 positions, adds candidates and authorises eligible voters.
3. Election Admin schedules the election (with re-authentication). A backup is taken.
4. The election is opened (manually, or automatically at the start time).
5. Returning Officer and Auditor monitor turnout and security alerts.
6. The election closes (manually, or automatically at the end time). A backup is taken.
7. Returning Officer calculates results. Verification recomputes them, compares hashes and checks the ballot/voter counts. Ties are resolved, then results are published (with re-authentication).
8. Reports are exported, and the election is archived.

---

## 15. Security threat model (STRIDE summary)

| Threat | Vector | Control |
|---|---|---|
| Spoofing a voter | Typing someone's Service Number | OTP to the registered email; the Service Number alone never grants access |
| OTP brute force | Guessing 6 digits | 5 attempts per code, 5-minute expiry, per-IP and per-voter rate limits, alerts |
| Enumeration | Probing which numbers are registered or have voted | Generic refusal message, rate limits, repeated-lookup alerts; VOTED status revealed only after OTP |
| Session fixation/hijack | Stolen cookie | Regeneration on login, HttpOnly/Secure/SameSite, short idle timeout, voting session bound to a server-side token |
| Double voting / race | Parallel submits, retries | Row locks, compare-and-set, unique token, idempotent replay |
| Tampering with votes | Admin or SQL edits | No UI for counts; DB triggers block UPDATE/DELETE on ballots/votes; results recomputed from votes; hash comparison on verification |
| Tampering with audit | Removing traces | Append-only trigger plus SHA-256 hash chain (`audit:verify`) |
| Repudiation | "I didn't open the election" | Re-authentication and audit of every privileged action, with IP and user agent |
| Information disclosure | Linking voter to vote | Secrecy boundary (§7); errors never show SQL/stack traces (`APP_DEBUG=false`) |
| IDOR | Changing IDs in URLs | UUID route keys and a permission check on every admin route; voter routes carry no IDs, since state comes from the authenticated session |
| Privilege escalation | Admin grants self | Only `manage_admins` can edit roles; re-authentication plus audit; a user cannot remove their own last admin role |
| Mass assignment | Posting `status=VOTED` | Status fields are guarded, and transitions happen only through services |
| XSS | Candidate bio, names | Blade escaping, strict CSP (`script-src 'self'`), no `{!! !!}` on user data |
| CSRF | Cross-site forms | Laravel CSRF tokens plus SameSite cookies |
| SQL injection | Search inputs | Eloquent/query builder bindings only; sort columns allow-listed |
| Malicious uploads | Photo polyglots, macro XLSX | MIME and extension allow-list, size and dimension limits, GD re-encoding, private storage, served with `nosniff`; imports parsed as data only |
| Replay | Resubmitting a ballot | Consumed tokens, and the VOTED state is terminal |
| DoS / availability | Flooding the OTP endpoint | Rate limits, queue isolation for OTP email, horizontal PHP-FPM scaling, pre-election load test |

## 16. Deployment architecture
Ubuntu 24.04 LTS · Nginx (TLS via Let's Encrypt or organisational CA, HTTP→HTTPS redirect, HSTS) · PHP-FPM 8.3+ · PostgreSQL 16+ on a private network with WAL archiving · systemd services for `queue:work` and a cron entry for `schedule:run` · nightly `pg_dump` plus pre-open and post-close dumps to encrypted off-host storage · logs rotated daily. Separate `.env` files for production and demo/staging. Demo mode refuses to start when `APP_ENV=production`. See `DEPLOYMENT.md` and `BACKUP-RESTORE.md`.

## 17. Testing strategy
- **Feature tests (PostgreSQL):** voter auth (valid/unknown/ineligible, OTP success/invalid/expired/brute force, resend), voting (eligible, ineligible, before open, after close, twice, wrong election/position candidate, inactive candidate, idempotent retry, transactional rollback), admin security (unauthenticated, per-role 403s, CSRF, IDOR, rate limiting, lockout, re-authentication), results (totals, percentages, ties, multi-seat, publish before close refused, no manual edit route, verification detects count mismatch), DB triggers (ballot/vote/audit immutability).
- **Unit tests:** TOTP (RFC 6238 vectors), phone normaliser, service-number normaliser, election state machine, import validator.
- **Manual/operational:** a load test script before each election, restore drill (`BACKUP-RESTORE.md`), and a scripted end-to-end demo run.
