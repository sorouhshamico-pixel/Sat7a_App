#!/usr/bin/env bash
#
# Database restore — see docs/MONITORING.md §Backup policy and
# docs/PRODUCTION_READINESS.md. Restores a backup produced by
# infrastructure/backup-database.sh (optionally decrypting it first) into
# a TARGET database. Deliberately never defaults the target to the live
# database name — a restore is destructive by nature, and a typo'd
# argument should not be able to silently wipe production. The target
# database is dropped and recreated fresh before restoring, so this is
# always a full replace, never a merge.
#
# Usage: infrastructure/restore-database.sh <backup-file> <target-database>
#   infrastructure/restore-database.sh storage/backups/tow_platform_2026....dump.enc tow_platform_restore_test

set -euo pipefail

if [ $# -lt 2 ]; then
  echo "Usage: $0 <backup-file> <target-database>" >&2
  exit 1
fi

BACKUP_FILE="$1"
TARGET_DB="$2"

if [ ! -f "$BACKUP_FILE" ]; then
  echo "Backup file not found: $BACKUP_FILE" >&2
  exit 1
fi

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"
ENV_FILE="$REPO_ROOT/apps/backend/.env"

read_env() {
  local key="$1"
  local default="${2:-}"
  if [ -f "$ENV_FILE" ]; then
    local value
    value=$(grep -E "^${key}=" "$ENV_FILE" | tail -n1 | cut -d'=' -f2- || true)
    if [ -n "$value" ]; then
      echo "$value"
      return
    fi
  fi
  echo "$default"
}

DB_HOST="${DB_HOST:-$(read_env DB_HOST 127.0.0.1)}"
DB_PORT="${DB_PORT:-$(read_env DB_PORT 5432)}"
DB_USERNAME="${DB_USERNAME:-$(read_env DB_USERNAME postgres)}"
DB_PASSWORD="${DB_PASSWORD:-$(read_env DB_PASSWORD)}"
PG_BIN="${PG_BIN:-/c/Program Files/PostgreSQL/17/bin}"

WORK_FILE="$BACKUP_FILE"
CLEANUP_DECRYPTED=""

if [[ "$BACKUP_FILE" == *.enc ]]; then
  if [ -z "${BACKUP_ENCRYPTION_KEY:-}" ]; then
    echo "This backup is encrypted — set BACKUP_ENCRYPTION_KEY to decrypt it." >&2
    exit 1
  fi
  DECRYPTED_FILE="${BACKUP_FILE%.enc}"
  openssl enc -d -aes-256-cbc -pbkdf2 \
    -in "$BACKUP_FILE" -out "$DECRYPTED_FILE" \
    -pass "pass:${BACKUP_ENCRYPTION_KEY}"
  WORK_FILE="$DECRYPTED_FILE"
  CLEANUP_DECRYPTED="$DECRYPTED_FILE"
fi

cleanup() {
  if [ -n "$CLEANUP_DECRYPTED" ] && [ -f "$CLEANUP_DECRYPTED" ]; then
    rm "$CLEANUP_DECRYPTED"
  fi
}
trap cleanup EXIT

echo "Recreating database '${TARGET_DB}' ..."
PGPASSWORD="$DB_PASSWORD" "$PG_BIN/psql" \
  --host="$DB_HOST" --port="$DB_PORT" --username="$DB_USERNAME" \
  --dbname=postgres -v ON_ERROR_STOP=1 \
  -c "DROP DATABASE IF EXISTS \"${TARGET_DB}\";" \
  -c "CREATE DATABASE \"${TARGET_DB}\";"

echo "Restoring ${WORK_FILE} into '${TARGET_DB}' ..."
PGPASSWORD="$DB_PASSWORD" "$PG_BIN/pg_restore" \
  --host="$DB_HOST" --port="$DB_PORT" --username="$DB_USERNAME" \
  --dbname="$TARGET_DB" --no-owner --no-privileges \
  "$WORK_FILE"

echo "Restore complete into '${TARGET_DB}'."
