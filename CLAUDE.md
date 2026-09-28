# NIMCOS E-VOTING — notes for contributors and coding agents

Laravel 13 / PHP 8.3+ / PostgreSQL. Read `docs/ARCHITECTURE.md` before changing anything in `app/Services/Voting`, `app/Services/Results` or the migrations.

## Non-negotiable invariants

- **Ballot secrecy:** never add a column, FK, timestamp, log entry or session value that links `voters` / `election_voters` / `voting_sessions` to `ballot_tokens` / `ballots` / `votes`. Anonymous-side models use `HasRandomUuid` (v4) and have no timestamps.
- **One ballot per voter:** submission goes only through `BallotSubmissionService::submit()` (lock order: elections → election_voters → voting_sessions → ballot_tokens).
- **Results come only from `votes`:** `ResultsCalculator` is the only writer of `result_tallies`. Never add an endpoint that accepts counts.
- **Election status changes only through `ElectionLifecycle`** (also enforced by a DB trigger).
- **Audit:** use `AuditLogger`; never log OTPs, passwords, tokens, ballot references or selections.
- **Authorisation by permission** (`permission:` middleware / `hasPermission()`), never by role name.
- **CSP:** no inline `<script>`, event handlers or `style=""` in Blade. Put behaviour in `public/assets/app.js` and styles in `public/assets/app.css`.
- Times are stored in UTC and displayed in Africa/Lagos with `display_time()`. Admin input goes through `to_utc_from_display()`.

## Commands

```bash
php artisan test                         # 102 tests against PostgreSQL nimcos_test (never a non-_test DB)
php artisan migrate --seed               # demo data only when NIMCOS_DEMO_MODE=true
php artisan queue:work --queue=otp,default
php artisan schedule:work                # nimcos:tick every minute
php artisan audit:verify
vendor/bin/pint                          # code style
```
