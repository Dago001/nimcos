# Testing

## Automated suite

The tests run against a **real PostgreSQL database** (`nimcos_test`), so row locks, composite foreign keys, CHECK constraints and integrity triggers are exercised exactly as in production. Each test runs inside a transaction that is rolled back. The suite refuses to run against any database whose name does not end in `_test`.

```bash
php artisan test                        # everything (102 tests)
php artisan test --filter=VotingTest    # one file
```

Configuration is in `phpunit.xml` (bcrypt at low cost for speed, array cache/session, synchronous queue, mail `array` driver, demo mode off).

| File | Covers |
|---|---|
| `Feature/VoterAuthenticationTest` | Valid / unknown / ineligible / suspended Service Number; identical refusal wording; OTP success, reuse, invalid, expired, brute-force lockout; hashed storage; invalidation on reissue; cooldown and request limits; rate limiting (429); lookup alerts; session regeneration |
| `Feature/VotingTest` | Eligible voter votes end to end; ineligible, not on roll, before open, after end time, after close; cannot vote twice; idempotent retry returns the same receipt; compare-and-set race guard; candidate from another election / wrong position / inactive; required positions; multi-seat limits and duplicates; **transaction rollback**; session expiry and revocation; one voter cannot use another's session |
| `Feature/BallotSecrecyTest` | Anonymous tables have no identifying or time columns and only the expected FKs; random v4 keys; raw ballot token never stored; audit log never contains reference or choices; logger scrubs secrets; live stats contain no candidate data |
| `Feature/ResultsTest` | Totals and percentages; single- and multi-seat tie detection without inventing a winner; no processing before close; full calculate → verify → tie → publish workflow; tampered tally detected; published results frozen by trigger; no route accepts vote counts; only `publish_results` may process results; results sealed while voting |
| `Feature/AdminSecurityTest` | Guest redirects; voter session grants no admin access; role/permission matrix; CSRF; lockout; login rate limit; session regeneration; MFA challenge and forced enrolment; re-authentication; malformed IDs → 404; voter URLs carry no identifiers; security headers; error pages leak nothing; last administrator manager protected |
| `Feature/ElectionLifecycleTest` | Create election + attach 14 positions (WAT→UTC); readiness checks; schedule → open → close; ballot locked once scheduled; closed election cannot reopen (application and DB); scheduler auto-open/close; ballots/votes/audit/VOTED immutable at DB level; no ballots into a closed election |
| `Feature/VoterImportTest` | Preview counts (duplicates, missing fields, bad Service Number, bad phone, missing, reused and bad email); nothing changes before confirmation; transactional create/update; no double confirmation; bad files rejected; duplicate Service Number on manual entry; formula-injection neutralisation |
| `Feature/DashboardAndEmailTest` | Dashboard lists every contestant with the live count (HTML and polling JSON); hidden when switched off or without `view_results`; voter codes, admin credentials and HIGH/CRITICAL alerts are emailed |
| `Feature/EligibilityAndCandidateTest` | Bulk authorisation filters; no granting while open but suspension allowed; VOTED final; per-election eligibility; photo re-encoding; non-image upload rejected; draft photos private; audit chain tamper detection |
| `Unit/SupportTest` | TOTP RFC 6238 vectors and window; Nigerian phone normalisation; election state machine |

## Parallel double-vote test (manual, staging)

The automated suite proves the compare-and-set logic. `scripts/race-test.php` proves it under **real concurrency**: it signs in a demo voter and fires N identical submissions simultaneously across several PHP workers.

```bash
# staging with demo data, several workers running (PHP-FPM, or several artisan serve ports)
php scripts/race-test.php https://staging.example 90007 50
```

Expected: every request answers `303 /ballot/receipt` with **one** receipt reference, and `ballots` increases by exactly one. Measured during development: 24 parallel submissions across 5 workers → 24 × 303, 1 ballot, 14 votes.

## Manual acceptance checklist (before each election)

- [ ] Complete a vote on an Android phone, an iPhone and a desktop browser (staging).
- [ ] The OTP email arrives through the production mail server within 30 seconds and is not marked as spam.
- [ ] The dashboard live vote count updates within 10 seconds of a vote; with live counts switched off, the results tab shows "sealed" while open.
- [ ] Close the staging election, then calculate → verify → publish; the public results page matches the PDF report.
- [ ] `php artisan audit:verify` passes.
- [ ] Keyboard-only run through the voter journey (Tab/Space/Enter), and a screen-reader spot check (TalkBack / VoiceOver).
