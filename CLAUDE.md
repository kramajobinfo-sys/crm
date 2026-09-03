# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

Krama Enterprise CRM — Laravel 12 (PHP 8.3) API + Vue 3 SPA, Docker-only. See `README.md` for
the stack/module overview and `docs/ARCHITECTURE.md` for full design. Read
`docs/BUILD_PROGRESS.md` first when resuming module work — it is the source of truth for what's
built, in what order, and lists "standing gotchas" learned the hard way in this project.

## Commands

Docker-only; see `docs/INSTALL.md` for the full setup sequence and day-to-day commands.

- **Always `migrate`, never `migrate:fresh`** — seeders are idempotent; fresh destroys data.
- `docs/INSTALL.md` / `docs/LAN_ACCESS.md` say the app runs on port `8080`; the current
  `docker-compose.yml` actually maps the frontend container to `8081:5173`. Verify with
  `docker compose ps` before trusting either doc.
- The backend test suite uses in-memory SQLite (`backend/phpunit.xml`) and runs with
  `composer test`. Add tenant-boundary coverage for every new tenant-owned resource. The frontend
  still has no lint/format/test script. `laravel/pint` is a dev dependency but unconfigured — run
  directly: `docker compose exec backend vendor/bin/pint`.

## Architecture

Layering and full design: `docs/ARCHITECTURE.md`. Controllers use the `ApiResponse` trait
(`app/Traits/ApiResponse.php`) for a uniform JSON envelope (`success()`, `error()`,
`paginated()`) — every endpoint returns `{success, message, data?, meta?}`.

### Multi-tenancy

Soft multi-tenant via `company_id`, enforced by the `BelongsToCompany` trait's global Eloquent
scope. **That scope only fires for an authenticated user** — seeders and any unauthenticated
context must pass `company_id` explicitly.

A platform-vs-tenant-admin identity split is mid-flight as of the current branch (modified
`ScopeCompany` middleware, `AuthService`, `RoleController`, `UserManagementController`, and the
new `TenantProvisioner` service / `add_team_key_to_permission_pivots` migration are uncommitted).
Check `git log`/`git diff` on those files before assuming the tenancy model in
`docs/ARCHITECTURE.md` is final — `spatie/laravel-permission` teams mode was just turned on as
part of it.

### Auth & authorization

RBAC via `spatie/laravel-permission`, permissions named `module.action`, gated by `permission:`
middleware in routes. Adding a permission means updating BOTH its definition and its role
assignment in `database/seeders/RolePermissionSeeder.php`. **After adding/changing permissions,
log out and back in in the UI** — permissions are baked into the frontend auth store at login and
persisted to `localStorage`; the router then silently redirects to the dashboard with no visible
error if a stale permission set is used.

### Caching & queues (Redis)

Queues: `default, emails, reports, webhooks, integrations, ai` — all consumed by the `queue`
container's `queue:work` command in `docker-compose.yml`. **Adding a new queue name requires
adding it to that command**, or jobs for it sit unprocessed forever with no error.

### Frontend

i18n: `src/locales/en.json` and `ar.json` (Arabic is RTL) — every new module needs strings in
both files. Known dev-loop friction:
- Vite sometimes doesn't see file changes over the Windows bind mount — fix with
  `rm -rf frontend/node_modules/.vite && docker compose restart frontend`.
- Synthetic DOM typing does not trigger Vue's `v-model`; set values via the native setter plus
  an `input` event, and remember Vue's DOM update is async.

### Module pattern

Every business module follows the same build shape (migration → model → service → FormRequests
→ controller/routes → API Resource → permissions → seeder → Vue page → locale strings → docs).
Full checklist: `docs/BUILD_PROGRESS.md` → "Definition of done, per module".

### Other operational notes

- `docker compose exec` runs as root; anything it creates under `backend/storage/` must be
  `chown -R www-data:www-data` or PHP-FPM cannot write to it.
- Never commit `.env`, `vendor/`, `node_modules/`, DB dumps, or `storage/app/public/*`.
- Composer advisory policy is set to non-blocking (`config.policy.advisories.block = false` in
  `backend/composer.json`) — deliberate, not a bug.
