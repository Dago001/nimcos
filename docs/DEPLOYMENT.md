# Production Deployment

## Server requirements

| Item | Recommendation (up to ~20,000 voters) |
|---|---|
| OS | Ubuntu 24.04 LTS |
| App server | 4 vCPU, 8 GB RAM, Nginx + PHP-FPM 8.3+ |
| Database | PostgreSQL 16+ (same host for small deployments, a dedicated private host preferred), 2 vCPU / 4 GB, SSD |
| Network | HTTPS only; the database port is never exposed publicly |
| Time | `systemd-timesyncd` or chrony **must** be active. The server clock decides when voting ends. |

## 1. System packages

```bash
sudo apt install nginx postgresql php8.3-fpm php8.3-{pgsql,mbstring,xml,zip,gd,intl,bcmath,curl} composer certbot python3-certbot-nginx gnupg
sudo adduser --system --group --home /var/www/nimcos nimcos
```

Create a dedicated PHP-FPM pool (`/etc/php/8.3/fpm/pool.d/nimcos.conf`) running as `nimcos`, listening on `/run/php/php8.3-fpm-nimcos.sock`. Set `pm.max_children` to about 30 for 8 GB of RAM. In `php.ini` set `expose_php = Off`, `upload_max_filesize = 20M`, `post_max_size = 25M`, `opcache.enable=1` and `opcache.validate_timestamps=0`.

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
