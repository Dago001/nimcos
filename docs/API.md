# Routes and Endpoints

The platform is a server-rendered application. Every state-changing endpoint is a form POST protected by session authentication, a CSRF token, rate limits and permission middleware. JSON endpoints exist only where the browser needs live data. Run `php artisan route:list` for the authoritative list (107 routes).

## Mapping from the specification's example API

| Specification | Implemented as | Notes |
|---|---|---|
| `POST /api/auth/voter/request-otp` | `POST /access` | body `service_number` |
| `POST /api/auth/voter/verify-otp` | `POST /verify` | body `code`; `POST /verify/resend` |
| `GET /api/elections/current` | `GET /elections` | elections open to the signed-in voter |
| `GET /api/elections/{id}/ballot` | `GET /ballot` | ballot for the active voting session (no id in the URL) |
| `POST /api/voting/session` | `POST /elections/{code}/start` | creates the voting session and ballot token |
| `POST /api/voting/ballot/submit` | `POST /ballot/submit` | idempotent; 303 → `/ballot/receipt` |
| `GET /api/admin/dashboard` | `GET /admin`, `GET /admin/dashboard/stats` (JSON) | |
| `GET/POST /api/admin/voters` | `GET /admin/voters`, `POST /admin/voters` | |
| `POST /api/admin/voters/import` | `POST /admin/imports` → preview; `POST /admin/imports/{id}/confirm` | two-step |
| `GET/POST /api/admin/elections` | `GET /admin/elections`, `POST /admin/elections` | |
| `GET/POST /api/admin/candidates` | `GET /admin/candidates`, `POST /admin/elections/{id}/candidates` | |
| `GET /api/admin/results` | `GET /admin/elections/{id}/results` | |
| `POST /api/admin/elections/{id}/close` | `POST /admin/elections/{id}/close` | password (+TOTP) required |
| `POST /api/admin/elections/{id}/publish-results` | `POST /admin/elections/{id}/results/publish` | password (+TOTP) required |

## Voter

| Method | Path | Middleware | Purpose |
|---|---|---|---|
| GET | `/` | throttle:public | Home page: how to vote, requirements, contact details, announcements |
| GET | `/vote` | guest:voter | Sign in (Service Number) |
| POST | `/access` | guest:voter, throttle:voter-access | Check register, issue OTP |
| GET/POST | `/verify` | guest:voter, throttle:otp-verify | OTP form / verify |
| POST | `/verify/resend` | throttle:voter-access | New OTP (invalidates the old one) |
| GET | `/elections`, `/elections/{code}` | auth:voter | Chooser / introduction |
| POST | `/elections/{code}/start` | auth:voter, throttle:ballot | Start ballot session |
| GET | `/ballot` | voting.session | Ballot |
| POST | `/ballot/review`, `/ballot/edit` | voting.session | Review / back-and-edit (selections are posted, not stored) |
| POST | `/ballot/submit` | auth:voter, throttle:ballot | Final submission |
| GET | `/ballot/receipt`, `/already-voted/{code}` | auth:voter | Receipt |
| POST | `/ballot/finish`, `/logout` | auth:voter | Sign out, clear the ballot cookie |

## Public

| Method | Path | Purpose |
|---|---|---|
| GET | `/results`, `/results/{code}` | Published results only |
| GET/POST | `/receipt/verify` | Confirms that a reference is recorded (never shows choices) |
| GET/POST/PUT/DELETE | `/admin/announcements…` | Announcements (permission `manage_announcements`); text only, links must be https or on this site |
| GET | `/media/candidates/{uuid}/photo` | Candidate photo (draft elections: admins only) |
| GET | `/up` | Health check |

## Administration (all require `auth:web` + MFA step + permission)

| Area | Routes | Permission |
|---|---|---|
| Dashboard | `GET /admin`, `GET /admin/dashboard/stats` | view_dashboard |
| Elections | `/admin/elections` CRUD, `schedule`, `unschedule`, `archive` | manage_elections |
| Open / close | `POST /admin/elections/{id}/open` · `/close` | open_election · close_election |
| Eligibility | `/admin/elections/{id}/eligibility` (+ `authorise-all`, add, `PATCH` status) | manage_elections |
| Positions | `/admin/positions`, `/admin/elections/{id}/positions` | manage_positions |
| Candidates | `/admin/candidates`, `/admin/elections/{id}/candidates`, `/admin/candidates/{id}` | manage_candidates |
| Voters | `/admin/voters…` | manage_voters (verify: verify_voters) |
| Imports | `/admin/imports…` | import_voters |
| Monitoring | `GET /admin/elections/{id}/monitor`, `…/monitor/stats` (JSON) | view_live_statistics |
| Results | `GET …/results`; `POST …/results/calculate`, `verify`, `ties/{position}`, `publish` | view_results; publish_results |
| Reports | `GET /admin/reports`, `GET /admin/reports/download?type=&format=&election=` | generate_reports (+ view_results / view_audit_logs by type) |
| Audit | `GET /admin/audit`, `POST /admin/audit/verify-chain`, `/admin/security-alerts` | view_audit_logs |
| Users/roles | `/admin/users…`, `/admin/roles` | manage_admins |
| Settings | `/admin/settings` | manage_system_settings |

## JSON: live statistics

`GET /admin/elections/{id}/monitor/stats` (session + `view_live_statistics`):

```json
{
  "summary": { "status": "OPEN", "eligible": 12450, "voted": 8742, "not_voted": 3708, "turnout": 70.22,
               "last_vote_at": "18:46:12", "sessions": { "active": 31, "completed": 8742, "expired": 120, "revoked": 4 },
               "server_time": "18:46:20", "accepting_votes": true },
  "hourly": [{ "label": "08:00", "value": 1203 }],
  "byCommand": [{ "label": "LAGOS COMMAND", "eligible": 2100, "voted": 1500, "turnout": 71.4 }],
  "security": { "failed_otp_last_hour": 3, "failed_lookups_last_hour": 9, "failed_admin_logins_last_hour": 0, "open_alerts": 1, "high_alerts": 0 }
}
```

Per-candidate figures before the election closes are returned only by `GET /admin/dashboard/stats` (key `tally`), only to users with `view_results`, and only when the election has live vote counts switched on (the default). The live monitor endpoint never returns them.
