#!/bin/bash
# NPCRM health monitor — runs from cron every 5 min.
# Checks app readiness, public path, all containers, disk, and queue failures.
# Alerts to Telegram ONLY on state change (down / recovered) to avoid spam.
# Telegram is optional: without /opt/npcrm/.monitor.env it just logs.
set -uo pipefail
cd /opt/npcrm 2>/dev/null || exit 1
DC="docker compose -f docker-compose.production.yml"
STATE=/opt/npcrm/.monitor-state
LOG=/opt/npcrm/monitor.log
CFG=/opt/npcrm/.monitor.env
DISK_WARN=85
SERVICES="web backend queue scheduler mysql redis"

# shellcheck disable=SC1090
[ -f "$CFG" ] && . "$CFG"
touch "$STATE" "$LOG"

ts(){ date '+%F %T'; }
tg(){ # $1=text (plain); no-op unless configured
  [ -n "${TG_BOT_TOKEN:-}" ] && [ -n "${TG_CHAT_ID:-}" ] || return 0
  curl -s --max-time 15 "https://api.telegram.org/bot${TG_BOT_TOKEN}/sendMessage" \
    -d chat_id="${TG_CHAT_ID}" --data-urlencode text="$1" >/dev/null 2>&1 || true
}
get_state(){ grep -E "^$1=" "$STATE" 2>/dev/null | head -1 | cut -d= -f2-; }
set_state(){ local tmp; tmp=$(mktemp); grep -vE "^$1=" "$STATE" 2>/dev/null > "$tmp" || true; echo "$1=$2" >> "$tmp"; mv "$tmp" "$STATE"; }

# manual delivery test
if [ "${1:-}" = "--test" ]; then
  if [ -n "${TG_BOT_TOKEN:-}" ] && [ -n "${TG_CHAT_ID:-}" ]; then
    tg "✅ NPCRM monitor test — alerts are wired ($(ts))"; echo "test alert sent to chat ${TG_CHAT_ID}"
  else echo "not configured: create $CFG with TG_BOT_TOKEN= and TG_CHAT_ID="; fi
  exit 0
fi

# report NAME OK|FAIL DETAIL — log always; alert on transition
report(){
  local name="$1" cur="$2" detail="$3" prev
  prev=$(get_state "$name"); prev="${prev:-OK}"
  echo "$(ts) [$name] $cur $detail" >> "$LOG"
  if [ "$cur" != "$prev" ]; then
    if [ "$cur" = FAIL ]; then tg "🔴 NPCRM: $name DOWN — $detail"
    else tg "✅ NPCRM: $name recovered — $detail"; fi
    set_state "$name" "$cur"
  fi
}

# 1) app readiness (local, authoritative: DB + Redis round-trip)
code=$(curl -s -o /tmp/npcrm_ready.$$ -m 8 -w '%{http_code}' http://127.0.0.1:8097/ready 2>/dev/null)
body=$(cat /tmp/npcrm_ready.$$ 2>/dev/null); rm -f /tmp/npcrm_ready.$$
if [ "$code" = "200" ] && printf '%s' "$body" | grep -q '"status":"ready"'; then
  report app_ready OK "$body"
else
  report app_ready FAIL "HTTP ${code:-timeout} ${body:0:120}"
fi

# 2) public end-to-end (proxy + TLS)
pcode=$(curl -s -o /dev/null -m 12 -w '%{http_code}' https://npcrm.nphomecenter.com/ 2>/dev/null)
if [ "$pcode" = "200" ]; then report public_https OK "200"; else report public_https FAIL "HTTP ${pcode:-timeout}"; fi

# 3) containers: running + healthy
for s in $SERVICES; do
  cid=$($DC ps -q "$s" 2>/dev/null)
  if [ -z "$cid" ]; then report "svc_$s" FAIL "no container"; continue; fi
  st=$(docker inspect -f '{{.State.Status}}' "$cid" 2>/dev/null)
  hl=$(docker inspect -f '{{if .State.Health}}{{.State.Health.Status}}{{else}}none{{end}}' "$cid" 2>/dev/null)
  if [ "$st" = running ] && { [ "$hl" = healthy ] || [ "$hl" = none ]; }; then
    report "svc_$s" OK "$st/$hl"
  else
    report "svc_$s" FAIL "$st/$hl"
  fi
done

# 4) disk usage of the /opt filesystem
duse=$(df -P /opt 2>/dev/null | awk 'NR==2{gsub("%","",$5); print $5}')
if [ -n "${duse:-}" ] && [ "$duse" -ge "$DISK_WARN" ]; then
  report disk FAIL "${duse}% used (>=${DISK_WARN}%)"
else
  report disk OK "${duse:-?}% used"
fi

# 5) queue failures: alert when failed_jobs grows
fj=$($DC exec -T mysql sh -c 'mariadb -uroot -p"$MYSQL_ROOT_PASSWORD" krama_crm -N -e "SELECT COUNT(*) FROM failed_jobs;"' 2>/dev/null | tr -dc '0-9')
if [ -n "${fj:-}" ]; then
  pfj=$(get_state failed_jobs_count); pfj=${pfj:-0}
  echo "$(ts) [failed_jobs] $fj (prev $pfj)" >> "$LOG"
  if [ "$fj" -gt 0 ] && [ "$fj" -gt "$pfj" ]; then
    tg "🟠 NPCRM: queue failed_jobs=$fj (was $pfj) — check 'php artisan queue:failed'"
  fi
  set_state failed_jobs_count "$fj"
fi

# keep log bounded
if [ "$(stat -c%s "$LOG" 2>/dev/null || echo 0)" -gt 1000000 ]; then tail -n 500 "$LOG" > "$LOG.tmp" && mv "$LOG.tmp" "$LOG"; fi
exit 0
