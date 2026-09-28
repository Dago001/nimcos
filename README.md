# NIMCOS E-VOTING

**Nigeria Immigration Multi-Purpose Cooperative Society Electronic Voting Platform**

A secure, auditable electronic voting platform for NIMCOS internal elections. Approved members vote with their Service Number and a one-time code sent to their registered email address. Ballots are anonymous, one per member per election. Administrators manage the register, elections, candidates and results under role-based access control.

| | |
|---|---|
| Stack | PHP 8.3+ · Laravel 13 · PostgreSQL 16+ · Blade + vanilla JS/CSS (no build step) |
| Home page | `/`: how to vote, requirements, contact details, announcements (pop-up and scrolling ticker) |
| Voter journey | `/vote`: Service Number → OTP → election → ballot (step-by-step on phones) → review → confirm → receipt |
| Roles | Super Admin · Election Administrator · Returning Officer · Auditor (permission-based) |
| Tests | 112 automated tests (PHPUnit, real PostgreSQL) + parallel race script |

## Key guarantees

- **Eligibility.** A Service Number alone never grants access. The voter must be on the approved register and on the election's roll, and must verify an OTP.
- **One member, one ballot.** Row locks, a compare-and-set update, unique ballot tokens and database triggers make double voting impossible, even under parallel retries (see [scripts/race-test.php](scripts/race-test.php)).
- **Ballot secrecy.** No column, foreign key, timestamp or log entry links a voter to their ballot. See [docs/ARCHITECTURE.md §7](docs/ARCHITECTURE.md).
- **Integrity.** Ballots, votes and audit logs are write-once at database level. Results are computed only from votes, verified by an independent recount, and frozen once published.
- **Auditability.** An append-only, SHA-256 hash-chained audit log (`php artisan audit:verify`).

## Quick start (development)

```bash
composer install
cp .env.example .env && php artisan key:generate
# set DB_* in .env (PostgreSQL), keep NIMCOS_DEMO_MODE=true for local demo data
php artisan migrate --seed
php artisan serve                       # http://127.0.0.1:8000
php artisan queue:work --queue=otp,default   # OTP delivery (separate terminal)
php artisan schedule:work                # election auto-open/close (separate terminal)
```

The demo seeder prints the admin accounts (all use the password `Demo-Only-2026!`). Demo voters use Service Numbers `90001` to `90024`. In demo mode the emailed code is also shown on screen. **Demo mode refuses to start in production.**

## Documentation

| Document | For |
|---|---|
| [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) | Architecture, ERD, secrecy model, threat model (Phase 1 design) |
| [docs/INSTALLATION.md](docs/INSTALLATION.md) | Setting up a development or staging environment |
| [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md) | Production server, Nginx, TLS, queue, scheduler |
| [docs/SECURITY.md](docs/SECURITY.md) | Security controls, residual risks, operating rules |
| [docs/DATABASE.md](docs/DATABASE.md) | Tables, constraints, triggers |
| [docs/API.md](docs/API.md) | Route and endpoint reference |
| [docs/ADMIN-GUIDE.md](docs/ADMIN-GUIDE.md) | Election officials: running an election end to end |
| [docs/VOTER-GUIDE.md](docs/VOTER-GUIDE.md) | Members: how to vote |
| [docs/BACKUP-RESTORE.md](docs/BACKUP-RESTORE.md) | Backups, restore drills, recovery |
| [docs/TESTING.md](docs/TESTING.md) | Running and extending the test suite |

## Project layout

```
app/Services/Voting      OTP, access, ballot sessions, validation, submission (the election core)
app/Services/Results     Calculation, verification, tie handling, presentation
app/Services/Elections   Election state machine and scheduler tick
app/Services/Voters      Register import (parse → preview → transactional commit)
app/Services/Audit       Hash-chained audit logger and verifier
app/Http/Controllers     Voter/, Admin/, Public/ (thin: validate → service → view)
database/migrations      Schema, CHECK constraints, composite FKs, integrity triggers
resources/views          Blade templates (voter, admin, public, reports)
public/assets            app.css / app.js (CSP-safe, no inline script or style)
deploy/                  Nginx, systemd and cron configuration
scripts/                 backup.sh, race-test.php
```
