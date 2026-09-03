#!/bin/bash
# Restore-drill: prove the latest NPCRM backup restores into a CLEAN, isolated
# MariaDB container and matches live. Never touches the live database.
set -uo pipefail
cd /opt/npcrm
DC="docker compose -f docker-compose.production.yml"
GZ=$(ls -t /opt/npcrm/backups/npcrm_*.sql.gz 2>/dev/null | head -1)
[ -n "$GZ" ] || { echo "NO_BACKUP_FOUND"; exit 1; }
echo "restoring from: $GZ ($(stat -c%s "$GZ") bytes)"

CN=npcrm_restore_test
PW="drill_$$"
docker rm -f "$CN" >/dev/null 2>&1 || true
docker run -d --name "$CN" -e MARIADB_ROOT_PASSWORD="$PW" mariadb:10.11 >/dev/null

echo -n "waiting for clean DB to finish init"
ready=0
for i in $(seq 1 90); do
  # poll a real AUTHENTICATED query — ping alone returns alive during the init phase
  if docker exec "$CN" mariadb -uroot -p"$PW" -e "SELECT 1" >/dev/null 2>&1; then ready=1; echo " ready"; break; fi
  echo -n "."; sleep 2
done
[ "$ready" = 1 ] || { echo " TIMEOUT"; docker logs --tail 25 "$CN" 2>&1; docker rm -f "$CN" >/dev/null 2>&1; exit 1; }

docker exec "$CN" mariadb -uroot -p"$PW" -e "CREATE DATABASE krama_crm;"
zcat "$GZ" | docker exec -i "$CN" mariadb -uroot -p"$PW" krama_crm
echo "import finished"

q(){ docker exec "$CN" mariadb -uroot -p"$PW" -N -e "$1" 2>/dev/null | tr -dc '0-9'; }
ql(){ $DC exec -T mysql sh -c "mariadb -uroot -p\"\$MYSQL_ROOT_PASSWORD\" -N -e '$1'" 2>/dev/null | tr -dc '0-9'; }

RT=$(q "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='krama_crm' AND table_type='BASE TABLE';")
RU=$(q "SELECT COUNT(*) FROM krama_crm.users;")
RL=$(q "SELECT COUNT(*) FROM krama_crm.leads;")
LT=$(ql "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=\"krama_crm\" AND table_type=\"BASE TABLE\";")
LU=$(ql "SELECT COUNT(*) FROM krama_crm.users;")
LL=$(ql "SELECT COUNT(*) FROM krama_crm.leads;")

echo "  restored : tables=$RT users=$RU leads=$RL"
echo "  live     : tables=$LT users=$LU leads=$LL"
docker rm -f "$CN" >/dev/null 2>&1
if [ "$RT" = "$LT" ] && [ "$RU" = "$LU" ] && [ "$RL" = "$LL" ] && [ -n "$RT" ]; then
  echo "RESTORE_DRILL_OK — backup is restorable and matches live"
else
  echo "RESTORE_DRILL_MISMATCH"; exit 1
fi
