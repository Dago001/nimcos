# Production Deployment

## Server requirements

| Item | Recommendation (up to ~20,000 voters) |
|---|---|
| OS | Ubuntu 24.04 LTS |
| App server | 4 vCPU, 8 GB RAM, Nginx + PHP-FPM 8.3+ |
| Database | PostgreSQL 16+ (same host for small deployments, a dedicated private host preferred), 2 vCPU / 4 GB, SSD |
| Cache/sessions | Redis 7+ (or Redis-compatible, e.g. Memurai on Windows for local dev), same host is fine — **required**, not optional, once real traffic is expected (see "Why Redis" below) |
| Network | HTTPS only; the database port is never exposed publicly |
| Time | `systemd-timesyncd` or chrony **must** be active. The server clock decides when voting ends. |

**Why Redis, not `SESSION_DRIVER=database` / `CACHE_STORE=database`:** with the database drivers, every single page view does a session read and a session write against PostgreSQL, on top of whatever that page's own queries do. At a few voters that's invisible; at thousands of concurrent voters hitting the site in the same narrow window, session I/O alone can become the bottleneck and slow down the one thing that must never be slow — the ballot submission's row lock. Redis removes that load from Postgres entirely. Sessions and cache are kept in separate Redis logical databases (`REDIS_SESSION_DB` / `REDIS_CACHE_DB`) so an admin running `php artisan cache:clear` mid-election can never sign every voter out.

**This does not, by itself, make `php artisan serve` production-capable.** That command is PHP's single-threaded development server — it answers one request at a time, full stop, regardless of how the app or database are tuned. It must never be used for a real election; use PHP-FPM behind Nginx as below.

### Sizing PHP-FPM for the vote

`pm.max_children` is the hard ceiling on how many requests PHP-FPM answers *at the same time*; every request beyond that queues. Each PHP-FPM worker for this app (Laravel, warmed OPcache) typically holds 40–70 MB resident; budget conservatively at 100 MB per worker to leave headroom for PostgreSQL, Nginx and Redis on the same box. For 8 GB of RAM with `pm.max_children = 30`, that is roughly 3 GB for PHP workers alone — comfortable, not tight.

30 concurrent workers does **not** mean only 30 of 20,000 voters can be served — a ballot page view is a fraction of a second once sessions and cache are off the database, so the real question is *requests per second*, not concurrent connections. 20,000 voters arriving over a multi-hour voting window is very different from 20,000 arriving in the first minute; size for the latter if the election is likely to open to a rush. Use `pm = dynamic` with `pm.start_servers` and `pm.min/max_spare_servers` set close to `pm.max_children` so PHP-FPM doesn't spend the first minutes of the election spinning up workers under load, and load-test the actual server (`scripts/race-test.php` proves correctness under concurrency, not throughput) before the real date with a tool such as `k6` or `wrk` pointed at `/vote` and the OTP endpoints.

## 1. System packages

```bash
sudo apt install nginx postgresql php8.3-fpm php8.3-{pgsql,mbstring,xml,zip,gd,intl,bcmath,curl} composer certbot python3-certbot-nginx gnupg
sudo adduser --system --group --home /var/www/nimcos nimcos
```

Create a dedicated PHP-FPM pool (`/etc/php/8.3/fpm/pool.d/nimcos.conf`) running as `nimcos`, listening on `/run/php/php8.3-fpm-nimcos.sock`. Set `pm.max_children` to about 30 for 8 GB of RAM (see "Sizing PHP-FPM for the vote" below). In `php.ini` set `expose_php = Off`, `upload_max_filesize = 20M`, `post_max_size = 25M`, `memory_limit = 512M`, `max_execution_time = 300`, `opcache.enable=1` and `opcache.validate_timestamps=0`.

**Why these values matter, not just defaults to copy:** `NIMCOS_UPLOAD_MAX_KB` in `config/nimcos.php` allows voter register uploads up to 20 MB, but PHP's own default `upload_max_filesize` (2M) and `post_max_size` (8M) are lower and silently reject the upload *before* Laravel's own validation runs — the browser shows a generic "file failed to upload" with no useful reason. The voter file parser also loads the whole workbook into memory at once (not streamed), so a large CSV/XLSX needs real headroom: PHP's default `memory_limit` (128M) is not enough for an import of several thousand rows. Check the *running* values, not just what's in `php.ini` — WAMP/XAMPP on Windows often ship a different `php.ini` than the one `php --ini` reports, or override it via `.user.ini`; confirm with `php -i | grep -i 'upload_max\|post_max\|memory_limit'` after changing it and restarting PHP-FPM (or `artisan serve`).

## 2. Database

```sql
CREATE ROLE nimcos LOGIN PASSWORD '<long random password>';
CREATE DATABASE nimcos OWNER nimcos ENCODING 'UTF8';
```

- In `pg_hba.conf`, allow `nimcos` only from the app server, with `scram-sha-256`.
- Do **not** enable `log_statement = 'all'` or `'mod'` in production: statement logs would record the timing of ballot inserts (see SECURITY.md).
- Enable WAL archiving for point-in-time recovery (BACKUP-RESTORE.md).

## 3. Application

```bash
sudo -u nimcos git clone <repository> /var/www/nimcos/releases/2026-09-01
cd /var/www/nimcos/releases/2026-09-01
sudo -u nimcos composer install --no-dev --optimize-autoloader
sudo -u nimcos cp .env.example /var/www/nimcos/shared/.env   # first deployment only; then edit it
ln -s /var/www/nimcos/shared/.env .env
ln -sfn /var/www/nimcos/releases/2026-09-01 /var/www/nimcos/current
```

### Production `.env` (essential values)

```
APP_ENV=production
APP_DEBUG=false
APP_URL=https://vote.nimcos.example.ng
APP_KEY=            # php artisan key:generate --show   (store a copy offline, see below)
LOG_LEVEL=info
SESSION_SECURE_COOKIE=true
SESSION_ENCRYPT=true
SESSION_DRIVER=redis         # never "database" — see "Why Redis" above
CACHE_STORE=redis
REDIS_HOST=127.0.0.1
NIMCOS_DEMO_MODE=false
NIMCOS_REQUIRE_ADMIN_MFA=true
MAIL_MAILER=smtp             # all voter codes and admin notices go by email
MAIL_HOST=smtp.example.org
MAIL_PORT=587
MAIL_USERNAME=...
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS=evoting@nimcos.example
MAIL_FROM_NAME="NIMCOS E-Voting"
TRUSTED_PROXIES=             # set only if behind a load balancer / reverse proxy
```

`chmod 640 .env`, owned by `nimcos:www-data`. **APP_KEY protects** encrypted sessions, cookies, MFA secrets, queued OTPs and OTP hashes. Store a copy offline with the backup keys. Never change it during an election.

```bash
php artisan migrate --force
php artisan db:seed --class=RolesAndPermissionsSeeder --force
php artisan db:seed --class=PositionSeeder --force
php artisan nimcos:create-admin superadmin@nimcos.org.ng "Name" --role=SUPER_ADMIN
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

### Storage permissions

```bash
chown -R nimcos:www-data storage bootstrap/cache
find storage bootstrap/cache -type d -exec chmod 2770 {} \;
```

Candidate photos and uploaded registers live in `storage/app/private` and are never web-accessible directly.

## 4. Nginx and TLS

```bash
sudo cp deploy/nginx/nimcos.conf /etc/nginx/sites-available/nimcos.conf   # edit server_name
sudo ln -s /etc/nginx/sites-available/nimcos.conf /etc/nginx/sites-enabled/
sudo certbot --nginx -d vote.nimcos.example.ng
sudo nginx -t && sudo systemctl reload nginx
```

HTTP redirects to HTTPS, and HSTS is sent by both Nginx and the application. Only `public/index.php` can execute.

## 5. Queue worker and scheduler

```bash
sudo cp deploy/systemd/nimcos-queue.service /etc/systemd/system/
sudo systemctl enable --now nimcos-queue
sudo cp deploy/cron/nimcos /etc/cron.d/nimcos
```

- The queue delivers OTPs (queue `otp`, highest priority) and processes register imports. **Voters cannot sign in if the worker is down.** Monitor it with `systemctl status nimcos-queue`.
- The scheduler opens and closes elections every minute, expires idle ballot sessions, verifies the audit chain hourly, and prunes old OTP records daily.
- After each deployment run `php artisan queue:restart`.

## 6. Log management

- Application logs: `storage/logs/laravel-YYYY-MM-DD.log`, rotated daily and kept for 30 days (`LOG_DAILY_DAYS`).
- Nginx logs: logrotate defaults. Restrict read access to operations staff.
- Logs never contain OTPs, passwords or ballot choices. Database errors are logged with a reference number shown to the user.

## 7. Before every election (checklist)

1. `php artisan about` shows the production environment with debug off.
2. `php artisan audit:verify` passes.
3. Send an OTP to a test mailbox through the real mail server (check it is not filed as spam), then remove that test voter from the roll.
4. Load-test staging with the expected peak (e.g. 500 sign-ins in 10 minutes).
5. Take the **pre-open backup**: `scripts/backup.sh pre-open`.
6. Confirm the server time with `timedatectl`.
7. Freeze deployments until results are published.

## 8. Updating

Deploy into a new release directory, run `composer install --no-dev`, `php artisan migrate --force` and the cache commands, switch the `current` symlink, then reload PHP-FPM and restart the queue. **Never deploy while an election is OPEN** unless it is an emergency fix approved by the Returning Officer.
