#!/bin/bash
# Safe NPCRM deploy: CI gate -> pre-backup -> build -> tag images -> migrate --force ->
# recreate -> health-gate. NEVER migrate:fresh/refresh. See MIGRATION_ROLLBACK.md / CI_CD.md.
# Skip the CI gate with SKIP_CI=1 (not recommended).
set -euo pipefail
cd /opt/npcrm
DC="docker compose -f docker-compose.production.yml"
echo "[0/7] CI gate"
if [ "${SKIP_CI:-0}" = "1" ]; then echo "      (skipped via SKIP_CI=1)"; else ./ci.sh || { echo "CI FAILED — aborting deploy"; exit 1; }; fi
echo "[1/7] pre-deploy backup";                ./backup-npcrm.sh
BK=$(ls -t backups/npcrm_*.sql.gz | head -1); echo "      -> $BK"
echo "[2/7] build images";                     $DC build
TAG=$(date +%Y%m%d-%H%M%S)
echo "[3/7] tag images :$TAG (for rollback)"
for s in backend web; do docker tag "krama-crm-production-$s:latest" "krama-crm-production-$s:$TAG" 2>/dev/null || true; done
echo "[4/7] migrate --force (safe)";            $DC run --rm backend php artisan migrate --force
echo "[5/7] recreate services";                $DC up -d
echo "[6/7] health gate (/ready)"
ok=0; for i in $(seq 1 15); do [ "$(curl -s -o /dev/null -m8 -w '%{http_code}' http://127.0.0.1:8097/ready)" = "200" ] && { ok=1; break; }; sleep 5; done
if [ "$ok" = 1 ]; then echo "[7/7] HEALTHY — DEPLOY_OK  (images :$TAG · db backup $BK)";
else echo "[7/7] UNHEALTHY — roll back DB: ./rollback-db.sh $BK CONFIRM  |  roll back code: docker tag <prev>:latest & up -d"; exit 1; fi
