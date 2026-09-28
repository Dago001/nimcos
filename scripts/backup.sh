#!/usr/bin/env bash
# NIMCOS E-VOTING database backup.
#
#   scripts/backup.sh nightly | pre-open | post-close | manual
#
# Produces a compressed, custom-format pg_dump, encrypts it with GPG for the
# configured recipient, records its SHA-256, and prunes nightly copies older than
# BACKUP_RETENTION_DAYS. Election-milestone backups (pre-open / post-close) are
# never pruned automatically. Reads DB_* settings from the application's .env.
set -euo pipefail

LABEL="${1:-manual}"
APP_DIR="${APP_DIR:-/var/www/nimcos/current}"
BACKUP_DIR="${BACKUP_DIR:-/var/backups/nimcos}"
RETENTION_DAYS="${BACKUP_RETENTION_DAYS:-30}"
GPG_RECIPIENT="${BACKUP_GPG_RECIPIENT:-}"

env_value() { grep -E "^$1=" "$APP_DIR/.env" | tail -1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//'; }

export PGHOST="$(env_value DB_HOST)"
export PGPORT="$(env_value DB_PORT)"
export PGDATABASE="$(env_value DB_DATABASE)"
export PGUSER="$(env_value DB_USERNAME)"
export PGPASSWORD="$(env_value DB_PASSWORD)"

mkdir -p "$BACKUP_DIR"
chmod 700 "$BACKUP_DIR"
STAMP="$(date -u +%Y%m%dT%H%M%SZ)"
FILE="$BACKUP_DIR/nimcos-${LABEL}-${STAMP}.dump"

pg_dump --format=custom --compress=9 --no-owner --file="$FILE"
pg_restore --list "$FILE" > /dev/null   # structural sanity check of the archive

if [[ -n "$GPG_RECIPIENT" ]]; then
    gpg --batch --yes --trust-model always --recipient "$GPG_RECIPIENT" --output "$FILE.gpg" --encrypt "$FILE"
    shred -u "$FILE"
    FILE="$FILE.gpg"
fi

sha256sum "$FILE" > "$FILE.sha256"
chmod 600 "$FILE" "$FILE.sha256"
echo "$(date -u +%FT%TZ) backup ok: $FILE ($(du -h "$FILE" | cut -f1))"

if [[ "$LABEL" == "nightly" ]]; then
    find "$BACKUP_DIR" -name 'nimcos-nightly-*' -mtime "+$RETENTION_DAYS" -delete
fi
