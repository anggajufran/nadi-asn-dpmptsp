#!/bin/sh
# Run as the VPS operator with Docker access. Isolated Certbot namespace avoids
# interacting with certificates managed by the Nginx Proxy Manager UI.
set -eu
exec 9>/home/anggauser/nadi-asn/.deploy/domain/renew.lock
flock -n 9 || exit 0
docker exec npm /opt/certbot/bin/certbot renew \
  --config-dir /etc/letsencrypt/nadi-asn \
  --work-dir /tmp/nadi-certbot \
  --logs-dir /data/logs/nadi-certbot \
  --cert-name nadiasndpmptspsulteng.online \
  --quiet --deploy-hook 'nginx -t && nginx -s reload'
