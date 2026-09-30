#!/usr/bin/env bash
set -euo pipefail
umask 077
project=/home/anggauser/nadi-asn
state="$project/.deploy/backup"
timeout 3600 "$project/infra/backup/project-backup.sh"
test -s "$state/project-last-success"
crontab -l > "$state/project-crontab-before.txt" 2>/dev/null || true
python3 - "$state/project-crontab-before.txt" <<'PY'
import pathlib,sys,subprocess
lines=[l for l in pathlib.Path(sys.argv[1]).read_text().splitlines() if '# nadi-project-backup' not in l]
lines.append('0 19 * * * /usr/bin/timeout 3600 /home/anggauser/nadi-asn/infra/backup/project-backup.sh --scheduled >> /home/anggauser/nadi-asn/.deploy/backup/project-cron.log 2>&1 # nadi-project-backup')
subprocess.run(['crontab','-'],input='\n'.join(lines)+'\n',text=True,check=True)
PY
printf 'Project backup every three calendar months at 03:00 WITA enabled.\n'
