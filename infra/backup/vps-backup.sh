#!/usr/bin/env bash
# Rootless backup for the deployed Docker stack; secrets remain outside Git.
set -euo pipefail
umask 077
project="${NADI_BACKUP_PROJECT:-/home/anggauser/nadi-asn}"
state="$project/.deploy/backup"
source "$state/backup.env"
: "${AGE_RECIPIENT_FILE:?}" "${RCLONE_CONFIG:?}" "${RCLONE_REMOTE:?}" "${BACKUP_DIR:?}"
age_bin="$state/tools/usr/bin/age"
rclone_bin="$state/tools/usr/bin/rclone"
mkdir -p "$BACKUP_DIR" "$state"
exec 9>"$state/backup.lock"
if flock -n 9; then
    :
else
    code=$?
    if ((code == 1)); then exit 0; fi
    exit "$code"
fi
if [[ "${1:-}" = "--scheduled" ]]; then
    if python3 "$project/infra/backup/policy.py" due "$state/last-success" --days "${BACKUP_INTERVAL_DAYS:-14}"; then
        :
    else
        code=$?
        if ((code == 1)); then exit 0; fi
        exit "$code"
    fi
fi
stamp="$(date -u +%Y%m%dT%H%M%SZ)"
run="$BACKUP_DIR/nadi-$stamp"
mkdir "$run"
stage="collect"
on_exit() {
    code=$?
    if ((code != 0)); then
        printf '%s FAILED stage=%s\n' "$(date -u +%FT%TZ)" "$stage" >> "$state/status.log"
        printf '%s\n' "$stamp $stage" > "$state/last-failure"
        # Keep encrypted partial outputs for diagnosis; no plaintext dump is saved.
    fi
}
trap on_exit EXIT
for name in nadi-asn-db-1 nadi-asn-app-1; do
    test "$(docker inspect -f '{{.State.Running}}' "$name")" = true
done
# pipefail ensures a failed dump/archive never looks like a successful encryption.
docker exec nadi-asn-db-1 pg_dump -U nadi_owner -d nadi_asn --format=custom --no-owner --no-acl |
    "$age_bin" -R "$AGE_RECIPIENT_FILE" -o "$run/database.dump.age"
docker exec nadi-asn-app-1 tar -C /var/www/storage/app/private -cf - . |
    "$age_bin" -R "$AGE_RECIPIENT_FILE" -o "$run/private-files.tar.age"
# Includes encryption keys needed to restore passwords/OTP, never the backup identity.
tar -C "$project" -cf - .deploy/app.env .deploy/db-owner-password .deploy/tls infra \
    app bootstrap config database resources routes public composer.json composer.lock \
    package.json package-lock.json artisan |
    "$age_bin" -R "$AGE_RECIPIENT_FILE" -o "$run/recovery-kit.tar.age"
for file in "$run"/*.age; do test -s "$file"; done
python3 - "$run" <<'PY'
import json,sys,pathlib,datetime
p=pathlib.Path(sys.argv[1])
(p/'manifest.json').write_text(json.dumps({
 'format':'nadi-backup-v1','created_at':datetime.datetime.now(datetime.timezone.utc).isoformat(),
 'database':'PostgreSQL custom dump; consistent DB snapshot',
 'files':'Live archive after database snapshot; no cross-volume atomic snapshot',
 'encrypted_components':['database.dump.age','private-files.tar.age','recovery-kit.tar.age'],
 'restore_requires':'External age identity; isolated PostgreSQL 16 restore and runtime DB grants',
},indent=2)+'\n')
PY
(cd "$run" && sha256sum *.age manifest.json > SHA256SUMS)
if [[ "${1:-}" = "--collect-only" ]]; then
    printf '%s COLLECTED snapshot=%s (not uploaded)\n' "$(date -u +%FT%TZ)" "$(basename "$run")" >> "$state/status.log"
    printf '%s\n' "$run" > "$state/last-collected"
    printf 'Encrypted local snapshot collected: %s\n' "$(basename "$run")"
    exit 0
fi
stage="upload"
remote="${RCLONE_REMOTE%/}/$(basename "$run")"
"$rclone_bin" --config "$RCLONE_CONFIG" copy "$run" "$remote" \
    --retries 3 --low-level-retries 5 --contimeout 20s --timeout 5m --transfers 2
stage="verify"
# Read back all objects, independent of provider checksum availability.
"$rclone_bin" --config "$RCLONE_CONFIG" check "$run" "$remote" --one-way --download \
    --retries 3 --contimeout 20s --timeout 5m
printf '%s VERIFIED snapshot=%s\n' "$(date -u +%FT%TZ)" "$(basename "$run")" >> "$state/status.log"
if [[ "${BACKUP_REPLACE_OLD:-false}" = true ]]; then
    stage="replace-old"
    # Exact dedicated namespace only; no purge of unrelated Drive directories.
    test "$RCLONE_REMOTE" = 'nadibackup:NADI-ASN-Backup'
    "$rclone_bin" --config "$RCLONE_CONFIG" lsf "$RCLONE_REMOTE" --dirs-only --max-depth 1 \
        --contimeout 20s --timeout 5m > "$state/remote-folders.txt"
    python3 "$project/infra/backup/policy.py" remote-plan "$state/remote-folders.txt" --latest "$stamp" > "$state/replace-plan.txt"
    while IFS= read -r old; do
        # Drive uses Trash by default; no global empty-trash operation.
        "$rclone_bin" --config "$RCLONE_CONFIG" purge "$RCLONE_REMOTE/$old" \
            --retries 3 --contimeout 20s --timeout 5m
        printf '%s REPLACED snapshot=%s\n' "$(date -u +%FT%TZ)" "$old" >> "$state/status.log"
    done < "$state/replace-plan.txt"
    python3 "$project/infra/backup/policy.py" prune-local "$BACKUP_DIR" --latest "$stamp"
fi
printf '%s OK snapshot=%s\n' "$(date -u +%FT%TZ)" "$(basename "$run")" >> "$state/status.log"
printf '%s\n' "$stamp" > "$state/last-success"
printf '%s\n' "$remote" > "$state/last-remote"
rm -f "$state/last-failure"
printf 'Backup encrypted, uploaded and verified: %s\n' "$(basename "$run")"
