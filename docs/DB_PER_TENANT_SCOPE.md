# DB-per-tenant — scope of work

**Status:** scoping only. Nothing here is built or committed. This document expands the
"DB-per-tenant — deliberately not built, not scaffolded" paragraph in
[`ARCHITECTURE.md`](ARCHITECTURE.md) into a concrete, repo-specific scope so that if the work is
ever greenlit, the decisions and costs are already surfaced.

**This is a scope, not a plan.** There are intentionally no week/day estimates on the work items
below. The single most important reason is spelled out in §1: the architecture fork (where the
identity table lives) changes several items by an order of magnitude, and it cannot be decided by
reasoning — it has to be decided by a one-day spike that either passes or eliminates an option.
Estimating before that spike runs is guessing.

Every line below is expressed as a **delta from what exists today**, with the file or count it
touches. A generic Laravel-multi-tenancy write-up would be worthless here; the value is in the
specific friction this codebase carries.

---

## 0. What "DB-per-tenant" would buy, and what it would not

Reasons a customer actually asks for this (name the benefits, not just the costs):

- **Hard data isolation.** A query can physically only reach one tenant's data. Today isolation is
  a `WHERE company_id = ?` predicate applied by a global scope — and the 2026-08-31 audit found
  four statements where that predicate silently vanished for a platform admin (a `delete from
  ai_predictions` with **no WHERE**). Physical separation removes that entire bug class.
- **Per-tenant backup / restore / export.** Currently one `mysqldump` of `krama_crm` holds every
  tenant co-mingled; restoring one tenant to a point in time means surgically extracting rows.
  Per-DB, it's a single dump/restore. This is often the whole reason the request comes in.
- **Per-tenant residency / compliance.** A tenant DB can live on a different host/region.
- **Blast-radius containment.** A runaway migration or a corrupt write hits one schema, not all.

What it does **not** buy, and what it costs:

- It does **not** improve the app's authorization logic — a bug that returns another user's data
  within the *same* tenant is unaffected.
- It multiplies every **operational** surface by N: migrations, backups, monitoring, connection
  pool sizing, and the "which DB am I pointed at right now" question on every long-lived process.

---

## 1. The spine: pre-auth tenant resolution (decide this first)

DB-per-tenant needs a reliable tenant identifier **before any DB query runs — including the query
that authenticates the user.** This is the constraint the whole design hangs on, and this repo
makes it unusually tight:

- The only Host-based resolver, [`TenantResolver`](../backend/app/Services/TenantResolver.php), is
  **deliberately unreliable by design** — its own docblock: *"reverse proxy, LAN IP, port-forward
  — this project supports all three… must only ever cost a logo, never a login."* It returns
  `null` cleanly for any Host it doesn't recognize. It **cannot** be promoted to "the thing that
  decides which database your login checks against" without contradicting the reason it exists
  (see [`LAN_ACCESS.md`](LAN_ACCESS.md) — the app is reached by reverse proxy, bare LAN IP, and
  forwarded port, none of which carry a trustworthy tenant Host).
- The customer portal already demonstrates the shape of this problem: portal login is **email-only
  with no tenant selector**, which forced global email uniqueness enforced in *application code*
  (MySQL has no partial unique index for it) — see the portal notes in
  [`BUILD_PROGRESS.md`](BUILD_PROGRESS.md), gap #4.
- Today `users` carries `company_id`, so **"which DB" and "who are you" are answered by the same
  row.** Splitting the database splits that row.

### The fork every downstream item depends on

**Where does the identity table (`users`) live?**

| Option | Identity in | Business data in | Login | Cost centre |
|---|---|---|---|---|
| **A** | central (`companies`, `users`, `plans`, `permissions`) | tenant DB | unchanged | **cross-DB relations** |
| **B** | tenant DB | tenant DB | needs a resolver we don't have | pre-auth resolution |
| **C** | central *directory* (`email → company_id → connection` + `companies`), full `users` in tenant DB | tenant DB | central lookup → then tenant | split identity |

- **Option A** keeps login exactly as it is, but **every relation that crosses the boundary
  breaks**: `Deal->owner`, `->with('assignee')`, any `whereHas()` on a user relation, and — pointedly
  — the `exists:users,id` validation rules whose *company scoping* was just fixed in the
  2026-08-31 audit. **Eloquent cannot eager-load or JOIN across two connections.** Those become
  application-side lookups, everywhere a staff record is referenced.
- **Option B** gives the cleanest isolation but needs a trustworthy pre-auth resolver, which §1
  above argues this deployment topology cannot reliably provide.
- **Option C** is the middle path: a tiny central directory answers "what connection does this
  email/subdomain map to", then the request switches to the tenant DB where the full `users` row
  (with roles, permissions, everything relational) lives. Cross-DB relations are avoided **for
  `users`** because everything relational is co-located in the tenant DB; only the routing lookup
  is central. **This is not automatically true of `companies` itself** — every tenant table carries
  `company_id` with an FK against it (§2.3), so `companies` cannot simply live "central" without
  breaking those constraints. The likely resolution is to **duplicate the single company row into
  its own tenant DB** (one company per DB, so it's a row, not a table of many) and keep only a
  minimal routing record central. State this explicitly for whichever option is chosen.

### 1a. Phase 0 kill-gate — a spike, run this before estimating anything

Do **not** design past this point on paper. Run a one-day spike:

1. Create a second MySQL schema; register a second connection in
   [`config/database.php`](../backend/config/database.php) (today it has exactly **one**
   connection, `mysql`).
2. Put `users` on connection A and `deals` on connection B.
3. Try, in tinker: `Deal::with('owner')->get()`, `Deal::whereHas('owner', …)->get()`, and a
   FormRequest carrying `exists:users,id`.
4. **Also test a tenant-table FK to a central `companies`** — put `companies` on connection A and a
   `deals`-like table with `company_id` + its FK constraint on connection B, and confirm what MySQL
   does with a cross-schema foreign key (it will not enforce it). This decides whether the company
   row must be duplicated into each tenant DB (§1 Option C note, §2.3).

Whatever fails there **eliminates Option A** (or confirms the exact list of call sites it would
force you to rewrite), and step 4 decides the `companies`/`currencies` placement question. This is
the cheapest possible falsification of the most expensive option, and it decides the shape of
everything below. **Nothing downstream should be estimated until this has run.**

---

## 2. Work items (each a delta from the current codebase)

Measured baseline: **33 migrations**, **100 model files** (≈**83** use the
[`BelongsToCompany`](../backend/app/Traits/BelongsToCompany.php) trait), **20 seeders**, **6 queue
names**, one Redis prefix, one DB connection.

### 2.1 Provisioning a tenant database
- The app DB user `krama` **has no `CREATE` privilege** outside `krama_crm` (confirmed via
  `SHOW GRANTS`, recorded in `ARCHITECTURE.md`). So creating a tenant DB needs **one of**: broaden
  `krama`'s grants (rejected — the whole point is least privilege), a **separate admin connection**
  with its own credentials used only for provisioning, or a **pre-created pool** of empty schemas
  handed out on registration.
- Decide **separately** whether each tenant also gets its own **DB user/credentials**. If every
  tenant DB is reached by the same `krama` login, the isolation is cosmetic — a leaked/confused
  connection string still reaches every schema. Real isolation = per-tenant DB user, which means
  storing (encrypted) per-tenant credentials in the central directory.
- Registration today ([`AuthService`](../backend/app/Services/AuthService.php) +
  [`TenantProvisioner`](../backend/app/Services/TenantProvisioner.php)) provisions roles + assigns
  the `Owner` role inside a single DB. It would additionally need to: create the schema, run the
  full migration set against it, and run the per-tenant seeders — transactionally enough that a
  half-provisioned tenant is cleaned up, not left dangling.

### 2.2 Per-tenant migration runner (largest *ongoing* cost)
- `php artisan migrate` targets **one** connection and records state in **one** `migrations` table.
  There are 33 migrations today and there will be more with every feature.
- Needs: per-tenant migration state, a loop over all tenant DBs, and — the hard part — a
  **partial-failure story**. If tenant 7 of 40 fails mid-migration, the estate is at **mixed schema
  versions**, and the app must either tolerate that or refuse to serve the lagging tenants.
- `CLAUDE.md`'s standing rule *"always `migrate`, never `migrate:fresh`"* now has to hold **across N
  databases**, including newly-provisioned ones that must catch up to HEAD before first use.
- This is not a one-off conversion cost; it is a **permanent tax on every future migration** in a
  repo that ships schema changes routinely (last one was 000033).

### 2.3 Existing-data extraction (the one-time conversion)
- `krama_crm` today holds **all tenants co-mingled**. Converting means, per tenant: create schema,
  migrate, then copy that tenant's rows out of the shared DB preserving FK integrity.
- **The global-vs-tenant table inventory is itself a deliverable, and it is *not* a clean grep.**
  A first-pass heuristic (`company_id` present in the create migration) splits 25 tenant-scoped
  migration files from 8 others — but that heuristic is wrong in two documented ways:
  - **ALTER migrations hide it.** 000024/000028–000033 add columns to tenant tables
    (`companies`, `contacts`, `quotations`, `pipeline_stages`, email tables) without re-declaring
    `company_id`, so they read as "global" but are not.
  - **`create_system_tables` (000006) bundles global and tenant tables together** — global
    `currencies` alongside tenant `settings`/`audit_logs`/`notifications`.
  So the true split requires **per-table inspection**, not a file grep.
- **"Central" must survive the same cross-DB test Option A fails (§1) — several candidates don't.**
  Applying that test to the naive "global" list:
  - **`companies`** — every tenant table has `company_id` **with an FK constraint** against it.
    Central `companies` + tenant-DB business tables = **cross-schema FKs, which MySQL will not
    enforce**. Resolution: **duplicate the single company row into its own tenant DB** (§1 Option C
    note) and keep only a minimal routing record central; the Phase 0 spike step 4 confirms this.
  - **`currencies`** — the app is multi-currency; if sales/invoice tables FK or join to it, it has
    the same cross-DB problem and must likewise be **duplicated per tenant DB** (it's small,
    seeded, and read-mostly — cheap to replicate).
  - **`plans` / `plan_features`** — safe to keep central: `companies.plan_id → plans` is
    **central-to-central** if the routing/company record is central, and `EnsureFeatureEnabled`
    reads it per request without joining a tenant table. Say this explicitly, because a reader
    applying §1's rule will otherwise assume it breaks.
  - **`permissions` catalogue** — see §2.7; likely duplicated per tenant DB to avoid a cross-DB
    `role_has_permissions` pivot.
  - **`migrations` ledger** — genuinely per-DB (each tenant DB tracks its own state, §2.2).
  So the only cleanly-central tables are the **routing directory** and **`plans`/`plan_features`**;
  most of the naive "global" set is actually **replicated-per-tenant**, not shared.
- FK ordering matters: extraction must follow dependency order (companies → users → customers →
  deals → …) or restore into the tenant DB fails on constraints.
- **Where the central directory physically lives is an explicit decision.** If it is the existing
  `krama_crm` with all tenant tables stripped out, §2.3's extraction and the directory's creation
  are the *same* operation (delete what you've copied out). If it is a **fresh** central schema,
  they are two separate jobs. Related: **Krama's own `is_platform` company** needs an answer too —
  does the platform org get its own tenant DB like everyone else, or does it stay resident in the
  central schema? (Cleanest: it is just another tenant DB, so platform-vs-tenant stays a flag, not
  a storage split.)

### 2.4 Queue workers (highest correctness risk)
- `docker-compose.yml` runs **long-lived `queue:work` processes across 6 queues**
  (`default,emails,reports,webhooks,integrations,ai`). A worker process is reused across jobs for
  many different tenants.
- Every job must **carry its tenant identity** and a queue middleware must **set the connection at
  job start and reset it at job end**. A leaked connection means a job for tenant B writes to
  tenant A's database — **silently**. Given this codebase has already produced a no-WHERE mass
  delete, treat this as **the single item most likely to cause real, irreversible damage**, and the
  one that most needs the test suite in §2.10 before it ships.

### 2.5 Scheduler / console commands
- `workflows:run-scheduled` runs `everyMinute()` **unauthenticated** (`routes/console.php`), and
  `krama-crm-env-gotchas` records we were **already bitten** by `auth()->user()` being null there.
- Under DB-per-tenant every scheduled command becomes a **loop over all tenant DBs**, each
  iteration setting/resetting the connection. Any command that today assumes one DB (all of them)
  is a conversion site.

### 2.6 Cache & Redis keys
- One Redis prefix derived from `APP_NAME` (`config/database.php`). Cache is **not** partitioned by
  tenant today; correctness relied on `company_id` being inside the *data*, not the *key*.
- Every `Cache::remember(...)` key needs a **tenant salt**, or tenant B reads tenant A's cached
  aggregate. The audit already caught key-construction bugs here (`topPerformers` omitted `$limit`
  from its cache key) — key hygiene in this repo is demonstrably fragile, so this is a real risk,
  not a formality.

### 2.7 spatie teams becomes partly redundant — decide explicitly
- Phase 1 of the SaaS conversion put a **team key on `model_has_roles` / `model_has_permissions`**
  (migration 000025) so roles are per-company inside one shared DB. **One company per DB makes that
  team key redundant.**
- Worse, the global `Permission` **catalogue** (`TenantProvisioner::syncPermissionCatalogue()`) is
  shared vocabulary. Under DB-per-tenant it must **either** be duplicated into every tenant DB
  **or** left central — and if central, `role_has_permissions` becomes a **cross-DB pivot**, which
  Eloquent cannot join. The likely answer is "duplicate the catalogue per tenant DB and drop the
  team key", but that **unwinds Phase 1's central work** and must be stated as an explicit decision,
  not discovered mid-build.
- Same question for [`BelongsToCompany`](../backend/app/Traits/BelongsToCompany.php) across the
  **≈83 models** that use it: once each DB holds exactly one company, the global scope's
  `WHERE company_id = ?` is redundant (though harmless, and arguably worth keeping as
  defence-in-depth). Removing vs. keeping it is a **scoping line item touching 83 files**, not an
  afterthought. `ScopeCompany` middleware's `setPermissionsTeamId()` call similarly becomes moot.

### 2.8 Platform console & support-access grants
- The platform console (`/api/v1/platform/*`) currently lists/aggregates tenants with **one
  query** against the shared DB. Cross-tenant list/rollup stops being a query and becomes **N
  round-trips** or a **central rollup/reporting table** kept in sync.
- Silver lining: `support_access_grants` **already** don't re-scope requests to a target tenant
  (documented limitation from Phase 4 — a grant is an authorization record, not a context switch).
  So there is **no working cross-tenant impersonation to preserve** — this piece may actually get
  *simpler* to reason about, because the honest behaviour ("platform admin sees the shared DB") is
  already the only behaviour.

### 2.9 Backup / restore (a benefit — name it)
- Today: one dump of `krama_crm`. Per-tenant restore = extract that tenant's rows from a whole-DB
  dump. Under DB-per-tenant: `mysqldump tenant_<id>` and restore in isolation. This is frequently
  the **reason the feature is requested**, so it belongs in the doc as an upside that offsets the
  operational multiplication in §2.2.

### 2.10 Test suite — a prerequisite, not a parallel nice-to-have
- `ARCHITECTURE.md` refused this work partly *because* there is **no test suite to catch a
  wrong-database write landing silently.** DB-per-tenant makes that failure mode both more likely
  (§2.4 queue leaks, §2.5 scheduler loops) and **invisible** (no error — just data in the wrong
  schema).
- The only regression net that exists is the **in-process query-count harness** (see
  `krama-crm-env-gotchas`), which boots Laravel once, **covers GET only**, and logs in a single
  user — it would itself need to become **per-tenant and cover writes** to be useful here.
- **Honest sequencing: tests first.** This is the one item that must precede the risky work
  (§2.2, §2.4, §2.5) rather than trail it.

---

## 3. Build it, or buy a package?

`ARCHITECTURE.md` already points at *"a purpose-built multi-database package rather than
hand-rolled connection switching"* — that instinct is correct. Hand-rolling per-tenant
migrations (§2.2), queue tenancy (§2.4), and cache tenancy (§2.6) is exactly what mature packages
exist to provide bootstrappers for.

**But do not assert a specific package is compatible from memory.** Verify, in this repo's own
terms, before recommending one:

- **Laravel 12 / PHP 8.3 support** — the project is pinned to Laravel 12 via a committed
  `composer.lock`.
- Whether it ships **bootstrappers for the three hardest-to-hand-roll pieces**: per-tenant
  migrations, queue tenancy, and cache tenancy.
- Whether its tenant-identification model can be driven by a **central directory lookup** (Option C
  in §1) rather than requiring a trustworthy Host header (which §1 argues this topology can't give).

**Local friction on adding the dependency at all:** `krama-crm-env-gotchas` and `CLAUDE.md` record
that `composer require` *"re-enters that failed [advisory] resolution"* against the pinned lock and
the `policy.advisories.block = false` workaround. So pulling in any package is **itself a scoped,
historically-painful task** — not a free line item. Budget for it explicitly.

---

## 4. Recommended sequence (contingent on §1a)

1. **Run the Phase 0 spike (§1a).** Decide Option A / B / C. *Everything below is contingent on the
   result and cannot be estimated before it.*
2. **Stand up a real test suite** covering writes, and make the query-count harness per-tenant
   (§2.10). Prerequisite, not parallel.
3. **Central directory + connection resolution** for the chosen option (§1).
4. **Provisioning + per-tenant migration runner** (§2.1, §2.2), with the partial-failure story
   designed up front.
5. **Queue + scheduler + cache tenancy** (§2.4–2.6) — the correctness-critical middle, gated behind
   the test suite from step 2.
6. **One-time data extraction** from the shared DB (§2.3), against the inventory produced as its
   own deliverable.
7. **Unwind/adjust spatie teams, `BelongsToCompany`, platform console** (§2.7, §2.8).

Treat this as **its own project**, not an extension of `TenantResolver` — consistent with the
existing `ARCHITECTURE.md` guidance.
