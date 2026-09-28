# Backup and Restore

## What must be protected

| Asset | Where | Backed up by |
|---|---|---|
| Database (register, elections, ballots, audit) | PostgreSQL | `scripts/backup.sh` (logical) + WAL archiving (point-in-time) |
| Uploaded files (candidate photos, import files) | `storage/app/private` | file-level backup (rsync/restic) |
| `.env`, especially `APP_KEY` | `/var/www/nimcos/shared/.env` | offline copy in a sealed envelope / password vault |

**Without the original APP_KEY**, encrypted sessions, MFA secrets and queued jobs cannot be read after a restore. Ballots, results and the audit log remain intact, but administrators must re-enrol MFA.

## Schedule

| When | Command | Retention |
|---|---|---|
| Nightly 01:15 (cron) | `scripts/backup.sh nightly` | 30 days (`BACKUP_RETENTION_DAYS`) |
| Immediately before opening an election | `scripts/backup.sh pre-open` | permanent |
| Immediately after closing (before calculating results) | `scripts/backup.sh post-close` | permanent |
| After publishing results | `scripts/backup.sh post-publish` | permanent |

Set `BACKUP_GPG_RECIPIENT` in the environment so that dumps are encrypted, and copy `/var/backups/nimcos` to storage off the server (another data centre or an encrypted cloud bucket) at least daily. Each dump has a `.sha256` file for integrity checking.

### Continuous archiving (recommended)

In `postgresql.conf`: `wal_level = replica`, `archive_mode = on`, `archive_command = 'test ! -f /var/backups/nimcos/wal/%f && cp %p /var/backups/nimcos/wal/%f'`. Take a weekly `pg_basebackup`. This permits recovery to any moment, for example just before an accidental change.

## Restore procedure

1. **Stop writes:** put the site in maintenance mode (`php artisan down`) and stop the queue worker.
2. Verify the dump: `sha256sum -c nimcos-post-close-….dump.gpg.sha256`, then decrypt: `gpg -d file.dump.gpg > file.dump`.
3. Restore into a **new** database first:
   ```bash
   createdb -O nimcos nimcos_restore
   pg_restore --no-owner --role=nimcos -d nimcos_restore file.dump
   ```
4. Validate the restored copy (point a staging `.env` at it):
   ```bash
   php artisan migrate:status        # all migrations "Ran"
   php artisan audit:verify          # hash chain intact
   ```
   and run the integrity query in DATABASE.md (ballots = voted = consumed tokens).
5. Swap the databases (rename `nimcos` → `nimcos_old`, `nimcos_restore` → `nimcos`), restore `storage/app/private` and the original `.env`, then `php artisan up` and start the queue worker.
6. Record the restore in the incident log (time, backup used, who authorised it).

## Restore testing

Perform a full restore into staging **before every election** and at least quarterly. Confirm that `audit:verify` passes and that the voter count and (for closed elections) the recalculated result hash match production. Record the date and outcome.

## During an election

- If the application server fails, the database holds all state. Redeploy the application and point it at the same database; voters simply sign in again.
- If the database is lost during voting, restore the latest WAL-archived state. Voters whose ballots were recorded remain marked as voted. **Any decision about extending voting time is for the Returning Officer and the electoral rules, not the technical team.**
