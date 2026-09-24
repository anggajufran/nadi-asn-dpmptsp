#!/usr/bin/env bash
set -euo pipefail
: "${PGHOST:?}" "${PGUSER:?}" "${PGDATABASE:?}" "${AGE_RECIPIENT:?}" "${RCLONE_REMOTE:?}" "${BACKUP_DIR:?}"
# Credentials must be delivered using PGPASSFILE and an isolated rclone config.
umask 077
mkdir -p "$BACKUP_DIR"
backup_file="$BACKUP_DIR/nadi-$(date -u +%Y%m%dT%H%M%SZ).dump.age"
pg_dump --format=custom --no-owner --no-acl | age -r "$AGE_RECIPIENT" -o "$backup_file"
test -s "$backup_file"
shasum -a 256 "$backup_file" > "$backup_file.sha256"
rclone copyto "$backup_file" "$RCLONE_REMOTE/$(basename "$backup_file")"
rclone copyto "$backup_file.sha256" "$RCLONE_REMOTE/$(basename "$backup_file.sha256")"
rclone check "$BACKUP_DIR" "$RCLONE_REMOTE" --one-way
# Retention and restore drill are separate reviewed operations; no automatic deletion here.
