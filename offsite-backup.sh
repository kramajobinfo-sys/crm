#!/bin/bash
# Encrypt the latest NPCRM backup (AES-256) and copy it OFF-SERVER. Off-site method, in order:
#   1) rsync to DEST (if set in .backup-offsite.env)
#   2) else email the .enc attachment to OFFSITE_EMAIL via the app's SMTP
#   3) else keep encrypted locally only
set -uo pipefail
cd /opt/npcrm || exit 1
DC="docker compose -f docker-compose.production.yml"
KEY=/opt/npcrm/.backup-key
CFG=/opt/npcrm/.backup-offsite.env
LOG=/opt/npcrm/backups/offsite.log
ENC_OPTS=(-aes-256-cbc -pbkdf2 -iter 200000 -salt)
DEST=""; SSH_KEY=""; OFFSITE_EMAIL=""
# shellcheck disable=SC1090
[ -f "$CFG" ] && . "$CFG"
[ -f "$KEY" ] || { echo "$(date '+%F %T') ERROR no backup key" >>"$LOG"; exit 1; }
GZ=$(ls -t backups/npcrm_*.sql.gz 2>/dev/null | head -1)
[ -n "$GZ" ] || { echo "$(date '+%F %T') ERROR no backup found" >>"$LOG"; exit 1; }
ENC="$GZ.enc"; NAME=$(basename "$ENC")
[ -f "$ENC" ] || openssl enc "${ENC_OPTS[@]}" -in "$GZ" -out "$ENC" -pass file:"$KEY"
STATUS="encrypted-local-only"

if [ -n "${DEST:-}" ]; then
  if rsync -az -e "ssh -i ${SSH_KEY:-$HOME/.ssh/id_ed25519} -o BatchMode=yes -o StrictHostKeyChecking=accept-new -o ConnectTimeout=20" "$ENC" "$DEST"/ >>"$LOG" 2>&1; then
    STATUS="rsync-> $DEST"; else STATUS="RSYNC-FAILED-> $DEST"; fi
elif [ -n "${OFFSITE_EMAIL:-}" ]; then
  BC=$($DC ps -q backend 2>/dev/null)
  if [ -n "$BC" ] && docker cp "$ENC" "$BC:/tmp/$NAME" 2>>"$LOG"; then
    MSG="NPCRM encrypted DB backup attached: $NAME ($(date '+%F %T')). Restore: openssl enc -d -aes-256-cbc -pbkdf2 -iter 200000 -in $NAME -pass file:.backup-key | zcat > dump.sql  (needs /opt/npcrm/.backup-key, kept off this server)."
    RES=$(docker exec -i -e BK_FILE="/tmp/$NAME" -e BK_TO="$OFFSITE_EMAIL" -e BK_NAME="$NAME" -e BK_MSG="$MSG" "$BC" php artisan tinker < /opt/npcrm/offsite-email.php 2>&1)
    docker exec "$BC" rm -f "/tmp/$NAME" 2>/dev/null || true
    if printf '%s' "$RES" | grep -q EMAIL_OK; then STATUS="emailed-> $OFFSITE_EMAIL";
    else STATUS="EMAIL-FAILED ($(printf '%s' "$RES" | grep -oE 'EMAIL_ERR.*' | head -c 140))"; fi
  else STATUS="EMAIL-FAILED (docker cp into backend)"; fi
fi

find backups -name 'npcrm_*.sql.gz.enc' -mtime +14 -delete
echo "$(date '+%F %T') $ENC ($(stat -c%s "$ENC")B) [$STATUS]" >>"$LOG"
echo "$(date '+%F %T') $ENC [$STATUS]"
