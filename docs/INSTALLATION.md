# Installation (development / staging)

## Requirements

| Component | Version | Notes |
|---|---|---|
| PHP | 8.3 or newer | extensions: `pdo_pgsql`, `pgsql`, `mbstring`, `openssl`, `sodium`, `gd`, `zip`, `xml`, `intl`, `fileinfo`, `bcmath` |
| Composer | 2.x | |
| PostgreSQL | 16 or newer | tested on 18 |
| Node.js | not required | CSS/JS are plain files in `public/assets` |

## 1. Get the code and dependencies

```bash
git clone <repository> nimcos && cd nimcos
composer install
cp .env.example .env
php artisan key:generate
```

## 2. Create the database role and databases

```sql
-- as the postgres superuser
CREATE ROLE nimcos LOGIN PASSWORD '<strong password>';
CREATE DATABASE nimcos      OWNER nimcos ENCODING 'UTF8';
CREATE DATABASE nimcos_test OWNER nimcos ENCODING 'UTF8';   -- for the test suite
```

Set `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` and `DB_PASSWORD` in `.env`. The connection is pinned to UTC in `config/database.php`. **Do not remove that setting:** every timestamp is stored in UTC and displayed in Africa/Lagos.

## 3. Migrate and seed

```bash
php artisan migrate --seed
```

The seeders:

| Seeder | Environment | Creates |
|---|---|---|
| `RolesAndPermissionsSeeder` | all | 16 permissions, 4 roles, default permission matrix |
| `PositionSeeder` | all | the 14 NIMCOS elective positions |
| `DemoSeeder` | only when `NIMCOS_DEMO_MODE=true` and not production | 4 demo admins, 24 fictitious voters, an **open** demo election with 42 candidates |

All demo records are flagged `is_test_data = true`, use Service Numbers 90001–90024 and `.test` emails, and are marked "Test" in the admin UI.

To create a real administrator (production or staging without demo data):

```bash
php artisan db:seed --class=RolesAndPermissionsSeeder
php artisan db:seed --class=PositionSeeder
php artisan nimcos:create-admin admin@nimcos.org.ng "Full Name" --role=SUPER_ADMIN
```

## 4. Run

```bash
php artisan serve                              # web
php artisan queue:work --queue=otp,default     # OTP emails, admin emails + import processing
php artisan schedule:work                      # auto open/close, session expiry, audit verification
```

- Voter site: `http://127.0.0.1:8000/`
- Administration: `http://127.0.0.1:8000/admin`

## 5. Email delivery

All notifications are sent by email:

- **Voters** receive their one-time verification code at the email address on the register (email is required for every voter and must be unique).
- **Administrators** receive a temporary password when their account is created or reset, and every administrator who can review security alerts is emailed when a HIGH or CRITICAL alert opens.

| `MAIL_MAILER` | Use |
|---|---|
| `log` | Development: messages are written to `storage/logs/laravel.log`. |
| `smtp` | Production. Set `MAIL_HOST`, `MAIL_PORT` (587), `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS`. |

Use a sending domain with SPF, DKIM and DMARC configured so codes are not filed as spam. The queue worker must run for email to be sent.

## 6. Staging vs production

Use a separate server, database and `.env` for staging/demo. Demo mode shows OTPs on screen and must never be used with the real register. The application refuses to boot with `NIMCOS_DEMO_MODE=true` or `APP_DEBUG=true` when `APP_ENV=production`.
