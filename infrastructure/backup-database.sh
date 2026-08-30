#!/usr/bin/env bash
#
# Database backup — see docs/MONITORING.md §Backup policy and
# docs/PRODUCTION_READINESS.md. Dumps the database in pg_dump's custom
# format (-Fc: compressed, and the only format pg_restore/pg_restore
# --list can work with selectively), optionally encrypts it at rest with
# OpenSSL AES-256-CBC when BACKUP_ENCRYPTION_KEY is set, and writes the
# result to BACKUP_DIR (default: storage/backups, gitignored). Off-site
# replication is deliberately NOT implemented here — this project has no
# real off-site storage target yet (no S3/equivalent credentials exist,
# matching the "no real vendor" pattern used everywhere else — see
# docs/SECURITY.md §Secrets). The extension point is clearly marked
# below: once a real target exists, add the upload call there — nothing
# else in this script needs to change.
#
# Usage: infrastructure/backup-database.sh
# Reads DB connection details from apps/backend/.env by default; every
# value can be overridden via environment variables of the same name.

set -euo pipefail

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
DB_DATABASE="${DB_DATABASE:-$(read_env DB_DATABASE tow_platform)}"
DB_USERNAME="${DB_USERNAME:-$(read_env DB_USERNAME postgres)}"
DB_PASSWORD="${DB_PASSWORD:-$(read_env DB_PASSWORD)}"
BACKUP_DIR="${BACKUP_DIR:-$REPO_ROOT/apps/backend/storage/backups}"
# Same PostgreSQL bin directory docs/DEPLOYMENT.md already documents for
# this Windows dev box; on a real Linux host pg_dump is normally already
# on PATH, so this only matters here.
PG_BIN="${PG_BIN:-/c/Program Files/PostgreSQL/17/bin}"

mkdir -p "$BACKUP_DIR"

TIMESTAMP="$(date -u +%Y%m%dT%H%M%SZ)"
DUMP_FILE="$BACKUP_DIR/${DB_DATABASE}_${TIMESTAMP}.dump"

echo "Backing up '${DB_DATABASE}' to ${DUMP_FILE} ..."

PGPASSWORD="$DB_PASSWORD" "$PG_BIN/pg_dump" \
  --host="$DB_HOST" \
  --port="$DB_PORT" \
  --username="$DB_USERNAME" \
  --format=custom \
  --file="$DUMP_FILE" \
  "$DB_DATABASE"

FINAL_FILE="$DUMP_FILE"

if [ -n "${BACKUP_ENCRYPTION_KEY:-}" ]; then
  ENCRYPTED_FILE="${DUMP_FILE}.enc"
  openssl enc -aes-256-cbc -pbkdf2 -salt \
    -in "$DUMP_FILE" -out "$ENCRYPTED_FILE" \
    -pass "pass:${BACKUP_ENCRYPTION_KEY}"
  rm "$DUMP_FILE"
  FINAL_FILE="$ENCRYPTED_FILE"
  echo "Encrypted: ${FINAL_FILE}"
fi

# --- Off-site upload extension point -----------------------------------
# No real off-site target configured yet. Once one exists (S3-compatible
# storage, matching how apps/backend/config/filesystems.php already
# documents documents-disk being "swappable to S3-compatible storage by
# changing that one config block"), add the upload call here, e.g.:
#   aws s3 cp "$FINAL_FILE" "s3://<bucket>/db-backups/$(basename "$FINAL_FILE")"
# -------------------------------------------------------------------------

echo "Backup complete: ${FINAL_FILE}"
