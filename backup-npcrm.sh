#!/bin/bash
# Nightly logical backup of the NPCRM (krama_crm) MariaDB database.
set -euo pipefail
cd /opt/npcrm
DC="docker compose -f docker-compose.production.yml"
BK=/opt/npcrm/backups
mkdir -p "$BK"
TS=$(date +%Y%m%d-%H%M%S)
OUT="$BK/npcrm_krama_crm_${TS}.sql.gz"

$DC exec -T mysql sh -c 'exec mariadb-dump --single-transaction --quick --routines --triggers --events -uroot -p"$MYSQL_ROOT_PASSWORD" krama_crm' | gzip -9 > "$OUT"

sz=$(stat -c%s "$OUT")
if [ "$sz" -lt 20000 ]; then
  echo "$(date '+%F %T') BACKUP_FAIL dump too small (${sz}B): $OUT" >&2
  rm -f "$OUT"
  exit 1
fi

# integrity: gzip must decompress cleanly
if ! gzip -t "$OUT" 2>/dev/null; then
  echo "$(date '+%F %T') BACKUP_FAIL corrupt gzip: $OUT" >&2
  exit 1
fi

find "$BK" -name 'npcrm_*.sql.gz' -mtime +14 -delete
echo "$(date '+%F %T') BACKUP_OK $OUT (${sz}B); kept $(ls -1 "$BK"/npcrm_*.sql.gz 2>/dev/null | wc -l) file(s)"
