#!/usr/bin/env bash
# Run interactively through SSH forwarding localhost:53682. Never print tokens.
set -euo pipefail
umask 077
state="/home/anggauser/nadi-asn/.deploy/backup"
mkdir -p "$state"
"$state/tools/usr/bin/rclone" --config "$state/rclone.conf" config create \
    nadibackup drive scope drive.file config_is_local true |
    sed -E '/^token = /d; /^client_secret = /d'
chmod 600 "$state/rclone.conf"
printf '\nGoogle Drive configuration saved privately. No token needs to be copied to chat.\n'
