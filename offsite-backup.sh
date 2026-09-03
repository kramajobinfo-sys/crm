#!/bin/bash
# Encrypt the latest NPCRM backup (AES-256) and, if a destination is configured in
# .backup-offsite.env, push it off-server. Runs nightly after backup-npcrm.sh.
set -uo pipefail
cd /opt/npcrm || exit 1
KEY=/opt/npcrm/.backup-key
CFG=/opt/npcrm/.backup-offsite.env
LOG=/opt/npcrm/backups/offsite.log
ENC_OPTS=(-aes-256-cbc -pbkdf2 -iter 200000 -salt)
DEST=""; SSH_KEY=""
# shellcheck disable=SC1090
[ -f "$CFG" ] && . "$CFG"
[ -f "$KEY" ] || { echo "$(date '+%F %T') ERROR no backup key" >>"$LOG"; exit 1; }
GZ=$(ls -t backups/npcrm_*.sql.gz 2>/dev/null | head -1)
[ -n "$GZ" ] || { echo "$(date '+%F %T') ERROR no backup found" >>"$LOG"; exit 1; }
ENC="$GZ.enc"
[ -f "$ENC" ] || openssl enc "${ENC_OPTS[@]}" -in "$GZ" -out "$ENC" -pass file:"$KEY"
STATUS="encrypted-local-only"
if [ -n "${DEST:-}" ]; then
  if rsync -az -e "ssh -i ${SSH_KEY:-$HOME/.ssh/id_ed25519} -o BatchMode=yes -o StrictHostKeyChecking=accept-new -o ConnectTimeout=20" "$ENC" "$DEST"/ >>"$LOG" 2>&1; then
    STATUS="pushed-> $DEST"
  else STATUS="PUSH-FAILED-> $DEST"; fi
fi
find backups -name 'npcrm_*.sql.gz.enc' -mtime +14 -delete
echo "$(date '+%F %T') $ENC ($(stat -c%s "$ENC")B) [$STATUS]" >>"$LOG"
echo "$(date '+%F %T') $ENC [$STATUS]"
