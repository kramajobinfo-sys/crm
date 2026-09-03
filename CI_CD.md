# NPCRM — CI/CD

The app is delivered as versioned ZIP releases, so CI/CD here is a **local pipeline** on
`192.168.1.141`: version-control the deployment, gate every deploy on tests + a frontend build,
deploy safely with per-release image tags, and roll back fast. (A push-based GitHub Actions
pipeline is possible later — see the end.)

## Pieces
| File | Role |
|---|---|
| `.git/` (in `/opt/npcrm`) | version control of the deployed source + config (secrets/deps/data are git-ignored) |
| `backend/Dockerfile.ci` | CI image = production backend image + `pdo_sqlite` (for the in-memory test DB) |
| `ci.sh` | **CI gate** — mirrors `.github/workflows/ci.yml` in throwaway containers |
| `deploy.sh` | **CD** — CI gate → backup → build → tag → `migrate --force` → recreate → health gate |
| `rollback-db.sh` | restore DB from any backup (see MIGRATION_ROLLBACK.md) |

## CI gate — `./ci.sh`
Runs the same checks as the shipped GitHub workflow, locally:
- **Backend**: `composer validate --strict`, `composer install`, `composer audit` (advisory),
  **`php artisan test`** — the full `tests/Feature` suite on an **in-memory SQLite** DB
  (needs `JWT_SECRET`, which ci.sh supplies as a throwaway test value). 44 tests today.
- **Frontend**: `npm ci`, `npm audit --omit=dev` (advisory), **`npm run build`** (Vite).
- Tests + build are **blocking**; dependency audits are **advisory** (warn, don't block —
  operator choice, so a new upstream advisory can't freeze deploys).
- Exit 0 = green. Run it any time to verify the tree is deployable.

## Deploy — `./deploy.sh`
1. **CI gate** (`ci.sh`) — aborts the deploy if red (`SKIP_CI=1 ./deploy.sh` bypasses; discouraged).
2. **Pre-deploy DB backup** (filename printed — keep it).
3. **Build** images.
4. **Tag** `krama-crm-production-{backend,web}:<YYYYMMDD-HHMMSS>` — the rollback point.
5. **`php artisan migrate --force`** (never `migrate:fresh`/`refresh`).
6. **Recreate** services, then **health-gate** on `/ready` (200). Prints the exact rollback
   commands and exits non-zero if it comes up unhealthy.

## Ingest a new release ZIP
```bash
cd /opt/npcrm
git stash -u 2>/dev/null || true                    # park local-only bits if any
# extract the new package OVER the tree, preserving env/uploads (never overwrite .env or storage):
unzip -o /path/krama-crm-production-<date>.zip -d /tmp/rel && \
  rsync -a --exclude '.env' --exclude 'deployment/storage/' --exclude 'backups/' /tmp/rel/*/ /opt/npcrm/
git add -A && git commit -m "release <date>"        # record exactly what will deploy
./deploy.sh                                          # CI-gated, safe, tagged deploy
```
Rollback of code = `git revert`/`checkout` the previous commit **or** re-tag a previous image
(`docker tag krama-crm-production-backend:<prevtag> krama-crm-production-backend:latest && \
docker compose -f docker-compose.production.yml up -d backend queue scheduler`). Rollback of
data = `./rollback-db.sh <backup> CONFIRM`.

## Version control notes
- `git` tracks source + compose + Dockerfiles + ops scripts + docs.
- **Git-ignored** (never committed): `.env`, `.backup-key`, `.backup-offsite.env`, `.monitor.env`,
  `vendor/`, `node_modules/`, `frontend/dist/`, `backups/`, `deployment/storage/`, `*.log`, `*.orig*`.
- Local repo only (no remote) — it's the deployment's own history.

## Optional: push-based GitHub Actions later
The shipped `.github/workflows/ci.yml` already runs CI on GitHub if you host the code there.
To auto-deploy on merge, add a **self-hosted GitHub Actions runner on .141** (so it can reach the
internal stack) whose job runs `./deploy.sh`, or a deploy job that SSHes in. Needs: a GitHub repo,
the runner, and secrets — a follow-up when you want it.
