#!/usr/bin/env bash
# Enable cron only after a complete offsite upload and read-back check succeeds.
set -euo pipefail
umask 077
project=/home/anggauser/nadi-asn
state="$project/.deploy/backup"
test -s "$state/rclone.conf"
chmod 600 "$state/rclone.conf"
timeout 1800 "$project/infra/backup/vps-backup.sh"
test -s "$state/last-success"
crontab -l > "$state/crontab-before.txt" 2>/dev/null || true
python3 - "$state/crontab-before.txt" <<'PY'
import pathlib,subprocess,sys
cron=pathlib.Path(sys.argv[1]).read_text()
lines=[line for line in cron.splitlines() if '# nadi-offsite-backup' not in line]
# VPS clock is UTC: 18:00 UTC = 02:00 WITA next day. Daily eligibility check
# gives a true 14-day interval, unlike cron day-of-month */14.
lines.append('0 18 * * * /usr/bin/timeout 1800 /home/anggauser/nadi-asn/infra/backup/vps-backup.sh --scheduled >> /home/anggauser/nadi-asn/.deploy/backup/cron.log 2>&1 # nadi-offsite-backup')
subprocess.run(['crontab','-'],input='\n'.join(lines)+'\n',text=True,check=True)
PY
printf 'Offsite upload verified; automatic backup every 14 days at 02:00 WITA enabled.\n'
