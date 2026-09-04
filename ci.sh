#!/bin/bash
# NPCRM local CI gate — mirrors .github/workflows/ci.yml, runs on this host in throwaway
# containers. Exit 0 = green (safe to deploy), non-zero = something failed.
# Tests + frontend build are blocking; dependency audits are advisory (warn, don't block).
set -uo pipefail
cd /opt/npcrm || exit 1
FAIL=0
echo "==================== NPCRM CI ($(date '+%F %T')) ===================="

echo "### Backend (composer validate + install + audit + php artisan test)"
docker image inspect npcrm-ci >/dev/null 2>&1 || docker build -q -f backend/Dockerfile.ci -t npcrm-ci ./backend >/dev/null
if docker run --rm -e COMPOSER_ALLOW_SUPERUSER=1 -e DB_CONNECTION=sqlite -e DB_DATABASE=:memory: \
      -e JWT_SECRET=ci_test_secret_0123456789abcdef0123456789abcdef \
      -v /opt/npcrm/backend:/src:ro npcrm-ci sh -c '
        set -e
        cp -r /src /tmp/app && cd /tmp/app && rm -rf vendor
        echo "-- composer validate";      composer validate --no-check-publish --strict
        echo "-- composer install";       composer install --no-interaction --prefer-dist -q
        echo "-- composer audit (advisory)"; composer audit --locked --no-dev || echo "   (audit warnings — not blocking)"
        echo "-- php artisan test";        php artisan test --compact
      '; then echo ">>> backend: PASS"; else echo ">>> backend: FAIL"; FAIL=1; fi

echo ""
echo "### Frontend (npm ci + audit + build)"
if docker run --rm -v /opt/npcrm/frontend:/src:ro -w /app node:20-alpine sh -c '
        set -e
        cp -r /src/. /app && rm -rf node_modules
        echo "-- npm ci";        npm ci --no-audit --no-fund --silent
        echo "-- vue-tsc type-check"; npm run type-check
        echo "-- npm audit (advisory)"; npm audit --omit=dev --audit-level=moderate || echo "   (audit warnings — not blocking)"
        echo "-- npm run build";  npm run build
      '; then echo ">>> frontend: PASS"; else echo ">>> frontend: FAIL"; FAIL=1; fi

echo "================================================================="
if [ "$FAIL" = 0 ]; then echo "CI: PASS ✅"; exit 0; else echo "CI: FAIL ❌"; exit 1; fi
