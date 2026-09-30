#!/usr/bin/env bash
# Complete deployed project, encrypted and independent of personnel snapshots.
set -euo pipefail
umask 077
project="${NADI_BACKUP_PROJECT:-/home/anggauser/nadi-asn}"
state="$project/.deploy/backup"
source "$state/backup.env"
: "${AGE_RECIPIENT_FILE:?}" "${RCLONE_CONFIG:?}"
age_bin="$state/tools/usr/bin/age"
rclone_bin="$state/tools/usr/bin/rclone"
base='nadibackup:NADI-ASN-Project-Backup'
snapshots="$state/project-snapshots"
mkdir -p "$snapshots"
exec 9>"$state/project-backup.lock"
if flock -n 9; then :; else code=$?; if ((code == 1)); then exit 0; fi; exit "$code"; fi
if [[ "${1:-}" = --scheduled ]]; then
    if python3 "$project/infra/backup/policy.py" due-months "$state/project-last-success" --months 3; then :
    else code=$?; if ((code == 1)); then exit 0; fi; exit "$code"; fi
fi
stamp="$(date -u +%Y%m%dT%H%M%SZ)"
run="$snapshots/nadi-project-$stamp"
mkdir "$run"
stage=archive
on_exit() {
    code=$?
    if ((code != 0)); then
        printf '%s FAILED stage=%s\n' "$(date -u +%FT%TZ)" "$stage" >> "$state/project-status.log"
        printf '%s %s\n' "$stamp" "$stage" > "$state/project-last-failure"
    fi
}
trap on_exit EXIT
folder="$(basename "$project")"
test "$folder" = nadi-asn
# Exclude the backup engine/state itself to avoid recursive archives and OAuth
# credentials. The app secrets in .deploy remain inside the encrypted archive.
tar -C "$(dirname "$project")" --exclude="$folder/.deploy/backup" \
    --exclude="$folder/.runtime/backup-keys" --exclude="$folder/.runtime/backup-check" \
    --exclude="$folder/.runtime/backup-tools" \
    --exclude="$folder/storage/logs" --exclude="$folder/bootstrap/cache/*.php" \
    -czf - "$folder" | "$age_bin" -R "$AGE_RECIPIENT_FILE" -o "$run/project.tar.gz.age"
test -s "$run/project.tar.gz.age"
python3 - "$run" <<'PY'
import pathlib,json,datetime,sys
p=pathlib.Path(sys.argv[1])
(p/'manifest.json').write_text(json.dumps({
 'format':'nadi-project-backup-v1','created_at':datetime.datetime.now(datetime.timezone.utc).isoformat(),
 'source':'Complete deployed /home/anggauser/nadi-asn tree',
 'archive':'project.tar.gz.age','root':'nadi-asn/',
 'exclusions':['.deploy/backup (backup snapshots/tools/OAuth)', '.runtime/backup-keys',
               '.runtime/backup-check','.runtime/backup-tools','storage/logs','bootstrap/cache/*.php'],
 'database_and_private_volumes':'Use separately scheduled NADI-ASN-Backup snapshot',
 'schedule':'Every 3 calendar months, 03:00 Asia/Makassar',
},indent=2)+'\n')
PY
(cd "$run" && sha256sum project.tar.gz.age manifest.json > SHA256SUMS)
stage=upload
remote="$base/$(basename "$run")"
"$rclone_bin" --config "$RCLONE_CONFIG" copy "$run" "$remote" \
    --retries 3 --contimeout 20s --timeout 5m --transfers 2
stage=verify
"$rclone_bin" --config "$RCLONE_CONFIG" check "$run" "$remote" --one-way --download \
    --retries 3 --contimeout 20s --timeout 5m
printf '%s VERIFIED snapshot=%s\n' "$(date -u +%FT%TZ)" "$(basename "$run")" >> "$state/project-status.log"
if [[ "${BACKUP_REPLACE_OLD:-false}" = true ]]; then
    stage=replace-old
    "$rclone_bin" --config "$RCLONE_CONFIG" lsf "$base" --dirs-only --max-depth 1 \
        --contimeout 20s --timeout 5m > "$state/project-remote-folders.txt"
    python3 "$project/infra/backup/policy.py" remote-plan "$state/project-remote-folders.txt" \
        --latest "$stamp" --prefix nadi-project- > "$state/project-replace-plan.txt"
    while IFS= read -r old; do
        "$rclone_bin" --config "$RCLONE_CONFIG" purge "$base/$old" --retries 3 --contimeout 20s --timeout 5m
        printf '%s REPLACED snapshot=%s\n' "$(date -u +%FT%TZ)" "$old" >> "$state/project-status.log"
    done < "$state/project-replace-plan.txt"
    python3 "$project/infra/backup/policy.py" prune-local "$snapshots" --latest "$stamp" --prefix nadi-project-
fi
printf '%s OK snapshot=%s\n' "$(date -u +%FT%TZ)" "$(basename "$run")" >> "$state/project-status.log"
printf '%s\n' "$stamp" > "$state/project-last-success"
printf '%s\n' "$remote" > "$state/project-last-remote"
rm -f "$state/project-last-failure"
printf 'Complete project backup uploaded and verified: %s\n' "$(basename "$run")"
