# Krama CRM — System Architecture

## High-level
```
Browser (Vue 3 SPA) --HTTPS/JWT--> Nginx --fastcgi--> PHP-FPM (Laravel 11)
                                                          |
                    +-------------------+-----------------+
                    v                   v                 v
                 MySQL 8            Redis 7          Queue worker
                                (cache/session/queue)  + Scheduler
```

## Backend layers
Request → Middleware (auth, throttle, company scope, audit) → FormRequest (validation)
→ Controller (thin) → Service (business logic, events, transactions)
→ Repository (data access, caching) → Eloquent Model + Observer (audit) → DB

## Multi-tenancy
Soft multi-tenant via `company_id` on every domain table. The `BelongsToCompany`
trait adds a global Eloquent scope (`where company_id = auth user's company`) and
auto-sets `company_id` on create. Platform admins (`users.is_platform_admin`) bypass the scope.

Converted to a sealed multi-tenant SaaS on top of that foundation: Krama is the platform/provider
(master), every other company is an independent tenant that self-manages — no parent/subsidiary
roll-up. Roles are per-company via `spatie/laravel-permission` **teams** (`config/permission.php`:
`teams => true`, keyed by `company_id`); permission *names* are a shared global vocabulary
(`TenantProvisioner::MODULES`), but each tenant gets its own copy of the role catalogue,
provisioned by `TenantProvisioner` at registration (self-service `POST /auth/register`) and for
existing companies via `RolePermissionSeeder`. The tenant's first user becomes **Owner** — full
control of their own org, never a platform admin. Team context is resolved per request in the
`ScopeCompany` middleware from the authenticated user's `company_id`.

**Editions (plans).** RBAC and subscription tier are two independent axes: a role answers "is this
*user* allowed", the tenant's `companies.plan_id` answers "did this *tenant* pay for this module".
Three cumulative tiers — Starter → Professional → Enterprise — each a module list defined in
`TenantProvisioner::planDefinitions()` and synced into `plans`/`plan_features` by
`RolePermissionSeeder`, the same idempotent-catalogue pattern as the permission vocabulary. The
`feature:{module}` route middleware (`EnsureFeatureEnabled`, aliased in `bootstrap/app.php`) gates
each module's top-level route group; a 403 with a plan-specific message surfaces automatically as
a toast via the frontend's existing response interceptor — no per-page handling needed. Platform
admins bypass it, same as the company scope. New self-service tenants start on **Starter**
(`TenantProvisioner::DEFAULT_PLAN`); companies that predate the plans system — the Krama master org
included — are backfilled onto **Enterprise** (`EXISTING_COMPANY_PLAN`) so introducing plans never
silently revoked something that already worked.

**Platform console.** `/api/v1/platform/*` — list/inspect tenants, change a tenant's plan
(`PlatformController::updatePlan` → `TenantProvisioner::provisionPlan`), record time-boxed
support-access grants. Gated by a dedicated `platform.admin` middleware (`isPlatformAdmin()`
only) — deliberately neither a `permission:` (a tenant role could never grant this to itself)
nor a `feature:` gate (a tenant's own plan must never be able to lock the platform out of
managing that tenant).

`support_access_grants` (reason + bounded `expires_at`, revocable) are **authorization records
only this phase** — they do not currently change what a request can read or write. A platform
admin already bypasses `BelongsToCompany`'s scope entirely (sees every tenant, writes land under
their own `company_id`); an active grant does not redirect either of those. Making a grant actually
switch request context to the target tenant means teaching `BelongsToCompany` (both its global
scope and its create-time `company_id` default) to resolve an *effective* company instead of
reading `auth()->user()->company_id` directly — every model that uses the trait, with no test
suite to catch regressions — left for a later phase rather than shipped half-working.

**Per-tenant subdomains (partial — branding only).** `companies.subdomain` (unique, nullable;
auto-generated from the company name at self-service registration) plus `TenantResolver` let a
request's `Host` header resolve to a tenant, exposed publicly via `GET /tenant-info` so the
login/register screens can show the right name/logo/color before anyone authenticates. This is
**deliberately read-only and advisory**: nothing authenticates, authorizes, or scopes data against
it, and a login's company is never checked against the resolved subdomain — this project already
supports reaching the API via a reverse proxy, a bare LAN IP, and a forwarded port (see
`LAN_ACCESS.md`), any of which can carry a `Host` header that doesn't match a real tenant, and that
must only ever cost a logo, never a login. `TenantResolver::resolveFromHost()` returns `null`
cleanly for every case it doesn't recognize.

**DB-per-tenant — deliberately not built, not scaffolded.** No column, no connection-switching
code, no service class exists for this. It was in the original Phase 5 scope and was cut after
review: unlike the resolver above, a half-built version is not inert — `config(['database.default'
=> ...])` at request time mutates state that every later query in that request (and, under
PHP-FPM worker reuse, potentially the *next* request) would resolve against, with no per-tenant
migration runner and no test suite to catch a wrong-database write landing silently. The app DB
user (`krama`) also only holds privileges on the single `krama_crm` schema (`SHOW GRANTS` — no
`CREATE`), so provisioning a real per-tenant database would additionally need either broader
grants or a separate administrative connection. If this becomes a real requirement, treat it as
its own project — most likely a purpose-built multi-database package rather than hand-rolled
connection switching — not an extension of the resolver above. A full repo-specific scope of what
that project would take (the pre-auth-resolution fork, the Phase 0 kill-gate spike, and the
per-item deltas from today's codebase) is in [`DB_PER_TENANT_SCOPE.md`](DB_PER_TENANT_SCOPE.md).

## Auth & authz
- JWT (tymon/jwt-auth): 15-min access token, 7-day rotating refresh, Redis blacklist
- 2FA optional per user (TOTP, pragmarx/google2fa)
- RBAC via spatie/laravel-permission: 9 roles, ~80 permissions across 27 modules
- Field-level control enforced in API Resources
- Auth throttling: 5 attempts/min/IP

## Caching (Redis)
| Resource        | Key                          | TTL   |
|-----------------|------------------------------|-------|
| Dashboard KPIs  | dash:c{co}:u{user}:kpi       | 5 min |
| Permissions     | spatie.permission.cache      | 24 hr |
| Lookups         | lookup:*                     | 24 hr |

## Queues
default, emails, reports, webhooks, ai — Redis-backed, exponential backoff, failed_jobs table.

## Security
CSRF (SameSite + header check), XSS (Vue auto-escape), SQLi (Eloquent bindings),
mass-assignment whitelists, rate limiting, HTTPS in prod, audit_logs + login_history,
2FA, encrypted 2FA secrets, sensitive-field scrubbing in audit trail.

## Frontend
Vue 3 + Vite + Pinia + Tailwind. Pinia stores: auth, ui (theme/locale/sidebar), notifications.
Axios client with automatic token refresh on 401. Lazy-loaded routes with permission guards.
i18n English + Arabic (RTL). Dark/light mode via Tailwind `class` strategy.
Executive Clean design, Corporate Blue palette.

The authenticated shell uses a route-aware workspace registry rather than one flat module list.
Workspaces group Home/My Work, CRM, Social Inbox, Customer Service, Business Operations, People,
Analytics, Integrations, Administration, and platform management. A navigation item is rendered
only when both axes allow it: the tenant plan exposes its module and the user has its view
permission. `/auth/me` carries plan module codes and refreshes persisted sessions on app mount.
Workspace selection changes navigation context while preserving every existing route/deep link.

Project Management is a separate delivery bounded context. A won Deal may create one Project
(`projects.deal_id` is tenant-unique); the commercial Account, owner, amount, and currency are
copied at handoff. Project members, milestones, and project tasks remain separate from CRM
Activities so sales follow-ups do not become delivery backlog. Project progress is materialized
from non-cancelled task completion and recalculated transactionally after task mutations.

Home & My Work provides a read model over assigned CRM Activities and Project Tasks. It does
not merge their tables or lifecycles: each row keeps its source and updates through the owning
module API. This gives users one daily queue without coupling CRM and project delivery domains.

Project Task collaboration adds two owned tables (`project_task_comments` and
`project_task_dependencies`) while reusing shared polymorphic attachments and timeline activity.
Dependency edges are limited to the same tenant and Project, checked for cycles, and incomplete
prerequisites block a task from advancing. Parent/subtask chains are independently cycle-checked.

Project time entries snapshot cost and bill rates from membership at creation, so later rate
changes never rewrite historical economics. Only approved time contributes to actual cost,
billable value, and task actual hours. Workload compares assigned estimated hours with one-person
business-day capacity while exposing summed project allocation separately to reveal overbooking.
An hourly idempotent scheduler sends one due-soon and one overdue in-app notification per task;
changing due date/assignee or reopening a task resets its notification stamps.

Project delivery automation remains inside the Project bounded context. Project templates store a
tenant-owned JSON blueprint containing milestones, task hierarchy, relative dates, estimates, and
recurrence settings; instantiation creates new owned records rather than sharing template rows.
Recurring tasks use a completion-driven chain: each completed occurrence can generate exactly one
future occurrence, guarded by `recurrence_generated_at`. Project automation rules are optionally
scoped to one Project and every execution is recorded with a unique event key, making scheduled
overdue processing idempotent. Management reports aggregate operational counts across the tenant,
while monetary totals use the tenant base currency and also expose a per-currency breakdown so
unconverted currencies are never silently combined.

## Module build order
1. Dashboard (done) → 2. Leads (done) → 3. Accounts + Contacts (done) → 4. Deals / Pipeline (done) → 5. Activities (done)
→ 6. Email (done) → 7. Sales (done) → 8. Purchase (done) → 9. Inventory (done) → 10. Helpdesk (done)
→ 11. Marketing (done) → Settings UI (done) → 12. HR (done) → 13. Reports (done) → 14. Workflow (done) → 15. AI Assistant (done)
16. Social/Chat Inbox (done) — built ahead of order on request

## Dynamics 365 Business Central (Module 17 — structure)
Sync framework: `BusinessCentralClient` (OAuth2 client-credentials + OData) → `SyncEngine`
→ per-entity `EntitySyncer` implementations → `bc_record_links` crosswalk.
Built on `Illuminate\Http`; **no new Composer dependency** was added, deliberately — a
`composer require` re-enters the advisory-policy resolution that already failed once and
would churn the committed `composer.lock`.

**What is real vs. what is not.** The client, engine, crosswalk, run/issue logging, queue job,
API and UI all work. Exactly **one** concrete syncer exists — `CompanySyncer` (BC `companies`,
pull-only) — chosen because `companies` is one of the few CRM tables that exists today, so the
framework is exercised by a real path rather than sitting behind an abstraction nothing
implements. Customer/Item/Invoice syncers ship with Modules 3, 7 and 9.

**No call has ever succeeded against a live BC tenant.** The error path *is* verified: with
dummy credentials the token request reaches real Entra ID and returns `AADSTS90002`, surfaced
as a structured result in ~5s with the message recorded on the connection — no hang, no
unhandled exception. A successful auth is what remains unproven.

- **Credentials**: `DYNAMICS_*` env vars are the documented path; per-connection DB values are
  the multi-tenant fallback and are encrypted. `client_secret` is `encrypted` + `hidden` +
  excluded from the Resource, so a leak needs three independent mistakes.
- **Timeouts are short and retries bounded** (20s / 8s connect / 2 retries) so an unreachable
  tenant cannot pin a queue worker.
- **Queue**: jobs go to the `integrations` queue, which `docker-compose.yml` was updated to
  consume. A job on a queue no worker reads fails silently forever — the most likely way this
  module would appear broken.
- **Tokens** are cached in Redis per connection, below their real lifetime.
- **Concurrency**: PATCH sends `If-Match` with the stored ETag; a 412 is logged as a conflict.

## Social / Chat Inbox (Module 16)

Social channel identities remain provider-specific rows in `chat_contacts`, while `linked_type`
and `linked_id` connect one identity to a tenant-owned Lead, Contact, or Account. Matching is
advisory and uses available email/phone data; staff must confirm the link. Creating a Lead from a
conversation goes through the normal Lead service so numbering, scoring, assignment, workflows,
and timeline behavior stay consistent. Both sides receive timeline entries, duplicate open Leads
are refused, and every target lookup is tenant-scoped before the polymorphic link is written.
Unified agent inbox across WhatsApp, Messenger, Instagram, TikTok, Telegram, SMS and web chat,
with **many accounts per provider** — 5 Facebook pages, 3 Instagram profiles, 2 Telegram bots
are all separate `chat_channels` rows. The rail filters by `channel_id` (one account) or
`channel_type` (every account of a provider), and each conversation row shows which account
it landed in, since a contact name alone is ambiguous across 5 pages.
Follows the standard layering: Controller (thin) → ChatService (transactions, business rules)
→ Eloquent + BelongsToCompany scope.

- **Channel-agnostic**: `chat_channels.type` plus a provider-agnostic message shape, so a new
  provider is a row, not a migration.
- **24h service window**: enforced in `ChatService`/`ChatController`; outbound replies outside
  the window are refused with 422 instead of being queued for a provider that would drop them.
  Internal notes stay allowed.
- **Media**: images, video, audio and PDF on any channel. Files land on the `public` disk under
  `chat/YYYY/MM/`, mimes are whitelisted, and each file is capped at 15 MB — deliberately far
  below PHP's 64 MB, because an upload that large times out mid-transfer and the client sees a
  network error instead of a validation message. A caption is optional when media is attached.
- **TikTok** is a supported channel type but is **not** subject to the Meta 24h service window;
  its constraints differ and were not verifiable here, so it defaults to open.
- **Outbound delivery is not wired up.** Messages persist with `status=queued`; no provider
  credentials are configured, so nothing is transmitted. A dispatcher job is the next step.
- **Transport is polling** (20s), not websockets — the stack has no broadcast driver, and adding
  one would mean new Composer dependencies. Swap to Reverb/Pusher when that tradeoff is worth it.
