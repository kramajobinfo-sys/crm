# NPCRM — Migration, Deploy & Rollback Runbook

Host: `192.168.1.141`  ·  Path: `/opt/npcrm`  ·  Stack: `docker compose -f docker-compose.production.yml`
Public: https://npcrm.nphomecenter.com (via proxy 192.168.1.118 → :8097)

## Golden rules
1. **Always back up before a deploy or migration.** `deploy.sh` does this automatically.
2. **Migrations use `php artisan migrate --force` only.** NEVER `migrate:fresh`, `migrate:refresh`,
   or `db:wipe` in production — they drop all data. Uploaded files under `deployment/storage/`
   must be preserved across deploys (they are: bind-mounted, not in the image).
3. **Test a backup before trusting it:** `./restore-drill.sh` restores the latest backup into a
   throwaway container and compares counts. `ENCRYPTED_BACKUP_RESTORE_OK` proves the `.enc` too.
4. The backend/queue/scheduler **code is baked into the image** (only `storage` is mounted), so
   any code change requires a rebuild + recreate — `deploy.sh` handles it.

## Standard deploy
```bash
cd /opt/npcrm && ./deploy.sh
```
Steps: pre-deploy backup → `docker compose build` → `migrate --force` → `up -d` →
health-gate on `/ready` (200). If it ends UNHEALTHY it prints the exact rollback command and
exits non-zero. The pre-deploy backup filename is printed — keep it.

## Rollback

### A. Bad migration / bad data → restore the DB
```bash
cd /opt/npcrm
./rollback-db.sh backups/npcrm_krama_crm_<TIMESTAMP>.sql.gz CONFIRM
```
- Works with plain `.sql.gz` or encrypted `.sql.gz.enc` (auto-decrypts with `.backup-key`).
- Takes a **safety snapshot of the current DB first**, then DROPs + recreates `krama_crm` and
  imports the chosen backup, then restarts backend/queue/scheduler.
- After: check `/ready` and spot-check data.

### B. Bad code → previous image
Recreate the previous container image if a build regressed behaviour:
```bash
docker images | grep krama-crm-production          # find the prior image id
docker tag <prior-image-id> krama-crm-production-backend:latest
docker compose -f docker-compose.production.yml up -d --force-recreate backend queue scheduler
```
(For a durable scheme, tag images per release, e.g. `:2026-09-03`, before `up -d`.)

## Backups
- **Local, nightly 02:30** — `backup-npcrm.sh` → `backups/npcrm_krama_crm_<ts>.sql.gz`
  (`mariadb-dump --single-transaction`, gzip, integrity-checked, 14-day retention).
- **Encrypted + off-site, nightly 02:45** — `offsite-backup.sh` encrypts the latest to
  `.sql.gz.enc` (AES-256, `openssl -pbkdf2`), and if `DEST` is set in `.backup-offsite.env`,
  rsyncs it off-server. Log: `backups/offsite.log`.
- **Encryption key: `/opt/npcrm/.backup-key` (chmod 600).**
  ⚠️ **An encrypted backup is useless without this key.** Keep a copy of `.backup-key` somewhere
  OFF this server (password manager / sealed store). If the host dies, you need both the `.enc`
  file and this key to restore.

### Pull encrypted backups off this box (from any other machine)
Run these FROM the machine that will hold the off-site copy:
```bash
# 1) the encrypted backups (safe to store anywhere — they're AES-256 encrypted)
rsync -avz -e ssh svsm@192.168.1.141:'/opt/npcrm/backups/*.sql.gz.enc' /path/offsite/npcrm/
# 2) ONCE, into a SEPARATE secure place (NOT next to the .enc files): the key
scp svsm@192.168.1.141:/opt/npcrm/.backup-key /secure/place/npcrm-backup-key
```
Automate step 1 with cron/Task Scheduler on that machine for a hands-off off-site copy. (Or set
`DEST` in `.backup-offsite.env` on .141 to have .141 push automatically instead.)

### Restore an off-site encrypted backup on a fresh host
```bash
openssl enc -d -aes-256-cbc -pbkdf2 -iter 200000 -in <file>.sql.gz.enc -pass file:.backup-key | zcat > dump.sql
# then create the krama_crm DB and: mariadb ... krama_crm < dump.sql   (or use rollback-db.sh)
```

## Health & monitoring
- `/ready` → `{"status":"ready",...}` (DB + Redis). `monitor-npcrm.sh` (cron */5) watches app,
  containers, disk, queue and logs to `monitor.log`.
