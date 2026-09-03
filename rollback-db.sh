#!/bin/bash
# Restore the NPCRM DB from a backup (.sql.gz or .sql.gz.enc). DESTRUCTIVE: replaces krama_crm.
# Takes a safety snapshot of the current DB first. Usage: ./rollback-db.sh <file> CONFIRM
set -euo pipefail
cd /opt/npcrm
DC="docker compose -f docker-compose.production.yml"
GZ="${1:-}"; OK="${2:-}"
{ [ -n "$GZ" ] && [ -f "$GZ" ]; } || { echo "usage: $0 <backups/npcrm_*.sql.gz[.enc]> CONFIRM"; exit 1; }
[ "$OK" = "CONFIRM" ] || { echo "refusing: this DROPS+recreates krama_crm. Re-run with CONFIRM as 2nd arg."; exit 1; }
echo "safety snapshot of CURRENT db first..."; ./backup-npcrm.sh || true
SRC="$GZ"; TMP=""
case "$GZ" in *.enc) TMP=$(mktemp); openssl enc -d -aes-256-cbc -pbkdf2 -iter 200000 -in "$GZ" -pass file:/opt/npcrm/.backup-key > "$TMP"; SRC="$TMP";; esac
echo "restoring from $GZ ..."
$DC exec -T mysql sh -c 'mariadb -uroot -p"$MYSQL_ROOT_PASSWORD" -e "DROP DATABASE IF EXISTS krama_crm; CREATE DATABASE krama_crm;"'
zcat "$SRC" | $DC exec -T mysql sh -c 'mariadb -uroot -p"$MYSQL_ROOT_PASSWORD" krama_crm'
[ -n "$TMP" ] && rm -f "$TMP"
$DC restart backend queue scheduler
echo "DONE — restored from $GZ, app restarted. Verify /ready and data."
