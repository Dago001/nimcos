# Security

This document describes the platform's security controls, what they protect against, and the operational rules that the technical controls depend on.

## Controls summary

| Area | Control | Where |
|---|---|---|
| Voter authentication | Service Number + 6-digit OTP (CSPRNG) to the registered email address; 5-minute expiry; 5 attempts; single use; new code invalidates old; 60 s resend cooldown; max 3 codes / 15 min | `app/Services/Voting/OtpService.php` |
| OTP storage | Only HMAC-SHA256(APP_KEY, id\|code) is stored. Queued OTP and admin-credential emails are encrypted (`ShouldBeEncrypted`). Logs never contain codes. | same, `app/Jobs/SendVoterOtp.php` |
| Enumeration | One generic refusal message for unknown, ineligible and suspended numbers; "already voted" is revealed only after OTP | `Voter/AccessController` |
| Admin authentication | Argon2id passwords (min 12 characters, mixed case, number, symbol); lockout after 5 failures for 15 minutes; TOTP MFA (enforceable); re-authentication with password (+TOTP) for every privileged action | `Admin/AuthController`, `Reauthenticator` |
| Authorisation | 16 permissions checked by middleware on every admin route; role names are never checked in code; separation of duties (Super Admin cannot publish results) | `routes/web.php`, `RequirePermission` |
| Sessions | Database driver, encrypted payload, HttpOnly, Secure (production), SameSite=Lax, 30-minute idle timeout, regenerated on sign-in / MFA / password change | `config/session.php`, `.env` |
| Ballot session | Server-side secret bound to the voter; one active session per voter per election; idle expiry; ballot token only in the voter's browser (encrypted, HttpOnly, SameSite=Strict) | `VotingSessionService` |
| Double voting | Row locks + compare-and-set + unique token + trigger that makes VOTED final + results invariant `ballots = voted = consumed tokens` | `BallotSubmissionService`, migrations |
| Replay / retries | Idempotent submission keyed on the ballot token; 303 redirect after POST | same |
| CSRF | Laravel CSRF tokens on every form + SameSite cookies | framework |
| XSS | Blade escaping everywhere (no unescaped output of user data); CSP `script-src 'self'`, no inline script or style | `SecurityHeaders` |
| Clickjacking / sniffing | `X-Frame-Options: DENY`, `frame-ancestors 'none'`, `nosniff`, `Referrer-Policy: no-referrer` | same |
| SQL injection | Query builder bindings only; sort/filter values allow-listed via enums | all queries |
| IDOR | UUID keys constrained by route patterns; admin access by permission; voter routes carry no identifiers (state from session) | `AppServiceProvider`, routes |
| Mass assignment | Status, lockout, MFA and ownership fields are not fillable; they change only in services | models |
| File uploads | Photos: extension + content MIME + dimensions + size; decoded and re-encoded by GD (discarding metadata and payloads); private storage; served with nosniff. Registers: CSV/XLSX only; parsed as data (formulas are never evaluated) | `CandidatePhotoService`, `VoterFileParser` |
| Spreadsheet export | Formula-injection neutralisation (`=`, `+`, `-`, `@`) in CSV; explicit cell types in XLSX | `ReportRenderer` |
| Rate limiting | Per IP and per Service Number (entry), per IP/session (OTP, ballot), per IP/email (admin login), per admin user; Nginx limits in front | `AppServiceProvider`, `deploy/nginx` |
| Error handling | `APP_DEBUG=false` enforced in production; SQL errors replaced by a friendly page with a reference number | `bootstrap/app.php` |
| Audit | Append-only (trigger) and SHA-256 hash-chained; verified hourly and on demand | `AuditLogger`, `audit:verify` |
| Monitoring | Security alerts for repeated failed OTPs, repeated unknown lookups, multiple sessions, duplicate ballots, tampered ballots, unauthorised admin actions, lockouts, failed verification, broken audit chain | `SecurityAlertService` |

## Ballot secrecy: guarantees and residual risk

**Guaranteed by design:**

- No database relation joins a voter to a ballot.
- Ballot and vote rows carry no timestamps and use random keys.
- The audit log records "voter X voted in election Y" only.
- Receipts carry a random reference that proves inclusion, not choice.
- Statistics are computed from the identity side only.
- Ballot selections are never stored in the session.

**Residual risk.** A person with *database superuser access* **and** the ability to observe the database *while voting happens* (for example, WAL streams, `pg_stat_activity`, statement logs, or physical row order in `votes` pages) could try to correlate the moment a ballot row appears with the moment a voter's row changes to VOTED. This is inherent to any single-server design without a mix-net. Mitigations:

1. Restrict production database superuser access to two named officials, and audit that access through infrastructure logs.
2. Never enable statement logging of INSERTs in production (`log_statement` must be `none` or `ddl`).
3. The application database role should own the schema but must not be used interactively. Administrators use read-only roles for any ad-hoc queries.
4. After results are published, a `VACUUM FULL votes, ballots` (or a dump and restore) rewrites physical row order.

## Operational rules

- MFA is mandatory for every administrator in production (`NIMCOS_REQUIRE_ADMIN_MFA=true`).
- Share temporary passwords only in person or through an approved secure channel.
- Review open security alerts at least hourly during voting. An alert is **not** evidence of fraud: verify before acting against any member.
- Never share screenshots of the results page before publication.
- Keep `APP_KEY`, database credentials and the SMTP password out of email and chat. Rotate the SMTP password after each election.
- **Live vote counts:** with live counts switched on, an official watching the dashboard at a quiet moment could see a count change just after a known member voted and so infer that member's choice. Where this matters (small electorates, or when observers are present at the terminal), untick "Show live vote counts" for the election, or restrict the "View results" permission to the Returning Officer during voting.

## Reporting a vulnerability

Report suspected vulnerabilities privately to the NIMCOS ICT Unit / system owner. Do not test against the production system during an election.
