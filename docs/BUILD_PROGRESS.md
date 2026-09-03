# Krama CRM — Build Progress Tracker

**Purpose:** this file is the source of truth for a long-running, multi-session build of
Modules 2–15. If a session ends mid-way, read this first, then continue at the first
step that is not `DONE`.

Status legend: `DONE` · `IN PROGRESS` · `TODO`

The agreed real-domain launch gate and post-launch Priority 0–4 product plan are recorded in
[`PRODUCT_ROADMAP.md`](PRODUCT_ROADMAP.md). This file remains the implementation status tracker;
the roadmap defines the approved order and scope of future work.

---

## Production stabilization (started 2026-09-01)

| Workstream | Status | Notes |
|---|---|---|
| Backend test baseline | DONE | PHPUnit configuration, in-memory SQLite connection, and `composer test` command added. |
| Tenant isolation tests | IN PROGRESS | First coverage proves tenant read isolation, automatic company assignment, and platform-admin bypass for `Lead`. Extend across every tenant-owned module and HTTP boundary. |
| Continuous integration | DONE | GitHub Actions verifies Composer, runs backend tests, audits production dependencies, and builds the frontend. |
| Private customer attachments | TODO | Move lead/deal/chat/email attachments behind authenticated downloads. |
| Production deployment | TODO | Immutable production Compose/images now built and smoke-tested; DB/Redis are private. Real domain/DNS/TLS deployment and rollback rehearsal remain. Package: `releases/krama-crm-production-20260903.zip`. |
| Backup and restore | TODO | Automate encrypted off-server backups and perform a restore drill. |
| Monitoring and alerting | TODO | Application errors, queue failures, integration health, and database/Redis readiness. |

### Core CRM terminology and workflow

| Workstream | Status | Notes |
|---|---|---|
| Accounts / Contacts / Deals navigation | DONE | Staff UI now exposes separate Accounts and Contacts modules and calls the opportunity module Deals. Legacy `/customers` and `/pipeline` URLs redirect safely. Backend table/API names stay compatible. |
| Standalone Contacts CRUD | DONE | Tenant-safe `/contacts` API, dedicated permissions and plan feature, list/detail/create/edit/delete UI, English + Arabic, global search, and 4 CRUD/isolation tests. Contacts still remain visible inside their Account. |
| Deal contact roles | DONE | Deals support multiple tenant-safe Account Contacts with buying roles and one primary designation, managed in the Deal create/edit and detail views. |
| Lead conversion wizard | DONE | Convert Lead creates or selects an Account, creates the Contact, optionally creates a Deal with the Contact as primary decision maker, and carries related data atomically. |
| Duplicate detection | DONE | Tenant-safe exact matching warns before Lead, Account, or Contact saves; Lead conversion also suggests matching Accounts. Indexed and covered by isolation tests. |
| Audited record merge | DONE | Lead/Account/Contact merge preview, per-field source selection, transactional relationship reassignment, soft-deleted duplicate, explicit audit record, permission and tenant safeguards. |
| CRM activity integration | DONE | Follow-up actions on Lead/Account/Contact/Deal details open a pre-linked Task; Activities supports tenant-validated Contacts and Quotations as related records. |
| Operational reminders | DONE | Users can create linked in-app or email reminders. A tenant-safe scheduler dispatches due reminders once, writes durable bell notifications, queues SMTP separately so it cannot block scheduled jobs, records failures through Sales Inbox email history, and the frontend polls unread notifications every minute. |
| Workspace navigation architecture | DONE | Replaced the mixed Main/Insights/System menu with route-aware workspaces: Home & My Work, CRM, Social Inbox, Customer Service, Business Operations, People, Analytics, Integrations, Administration, and platform management. Items are filtered by both tenant plan and user permission; existing URLs remain unchanged; mobile now has a real drawer. Project Management can join as a workspace when its domain is built. |

---

## Resume here

> **BUILD COMPLETE — all 15 queue modules done** (+ Settings UI)
> Last updated: 2026-08-28
>
> Every module in the queue is built, verified and committed: M2–M15 plus the Settings UI, on
> top of the pre-existing Foundation, M1 Dashboard, M16 Chat and M17 Dynamics structure. All
> dashboard widgets show real data. Migrations run through `000023`; the full seeder chain runs
> clean and idempotently. Nothing outstanding — future work is optional polish (e.g. wiring
> workflow event/schedule triggers, real provider integrations for email/SMS/chat outbound, a
> live LLM behind the AI assistant, and the remaining M17 BC syncers).
>
> **Post-run additions** (each built + verified + committed on request):
> - User Profile, Change Password, and Register/Create-account flow.
> - **Social Inbox Settings** (Module 18): connect each channel's provider API manually.
>   `ChannelService` + `ChannelSettingsController` over the existing encrypted
>   `chat_channels.config`; per-type credential schema; secrets write-only (never returned);
>   structural "test connection". UI at `/app/inbox-settings` (`chat.manage_channels`).
>   No live provider handshake — clean seam for real integrations later.
> - **Real SMTP email sending** (closes the one structural gap in M6): `email_accounts.config`
>   (encrypted, same pattern as chat) holds real SMTP credentials per account; `SmtpMailer`
>   sends via `Illuminate\Mail\MailManager::build()`. A failed send never errors the request —
>   the `emails` row persists with `status=failed` + a human-readable `error`, retryable via
>   `POST /emails/{id}/send`. `POST /email-accounts/{id}/test` sends a real test email (no
>   cheaper live SMTP handshake exists). UI: "Email accounts" manager on the Email page
>   (`email.manage_templates`), with a masked/write-only password field. Verified against a new
>   local Mailpit container (`docker-compose.yml`, :8025) — a real send-and-confirm round trip,
>   not just a structural check — and a genuine failure path (unreachable host) confirmed to
>   fail cleanly with a clear error rather than a 500. Also caught and fixed a real
>   frontend race condition (a stale account fetch could overwrite a newer selection after save).
> - **Workflow event/schedule triggers** (closes the last gap in M14 — `run()` previously only
>   fired manually): `Workflow::EVENTS` defines the entity → event catalogue (`lead.created`,
>   `deal.stage_changed`/`won`/`lost`, `ticket.created`/`status_changed`/`escalated`,
>   `customer.created`, `invoice.created`/`paid`, etc). `WorkflowService::fireEvent()` is called
>   synchronously, in-transaction, from the exact spot each service already derives that
>   transition (`LeadService`, `DealService`, `HelpdeskService`, `CustomerService`,
>   `SalesService::recalcInvoice()` for `invoice.paid`) — no observers, no polling. Schedule
>   triggers reuse the existing `workflows.schedule_cron` column: a new `workflows:run-scheduled`
>   command (registered `everyMinute()` in `routes/console.php`, so the always-running scheduler
>   container drives it) parses cron via `dragonmantank/cron-expression` (already a dependency —
>   no `composer require` needed) and runs any workflow due since `last_run_at`. Fixed two real
>   bugs found while wiring this: `doCreateTask`/`doSendEmail` read `auth()->user()->company_id`,
>   which is null on the unauthenticated scheduler process — switched to `$workflow->company_id`.
>   Frontend: the workflow form previously had no way to set `trigger_event`/`schedule_cron` at
>   all (dead UI gap, not just missing wiring) — added an event dropdown and a cron text input,
>   gated on `trigger_type`, sourced from `GET /workflows/meta`'s new `events` map. Verified for
>   real via curl: created an event-workflow with a `create_task` action, created a live Lead
>   through the normal API, and confirmed both the `workflow_runs` row (`status=success`) and the
>   actual `Task` row it created, correctly linked to the lead — then created a schedule-workflow
>   (`* * * * *`) and confirmed `workflows:run-scheduled` picks it up once the minute ticks and not
>   before. Test workflows/leads/tasks cleaned up after.
> - **Real outbound webhooks** (closes the last structural action in M14 — `webhook` previously
>   just logged `"Webhook noted: {url}"`): `WebhookDispatcher` sends an HMAC-signed HTTP POST with
>   a 5s timeout and no redirect-following. The signing key is derived from `APP_KEY` + the
>   workflow id rather than stored, since action config (including the target URL) is returned
>   verbatim by `workflows.view` — nothing new to leak. A basic SSRF guard rejects non-http(s)
>   schemes and literal loopback/private/link-local targets outside `APP_ENV=local` (accepted
>   trade-off: this blocks the obvious cases, not DNS-rebinding). Verified for real: a PHP
>   built-in server run inside the backend container (host.docker.internal from the container
>   couldn't reach a Windows-host listener, blocked by something upstream of the app — routing
>   the receiver into the same container sidestepped it) received a live POST from a real
>   `lead.created` event, and the HMAC was independently recomputed from the exact captured bytes
>   and matched the sent header. Also confirmed the guard actually blocks
>   `127.0.0.1`/`169.254.169.254`/`10.x`/`localhost` outside local env, and that a dead-port
>   target fails the run cleanly (`status=failed`, readable message) without raising past `run()`.
>
> Additionally: the SaaS/multi-tenant conversion (Phases 0–5 — platform vs tenant admin split,
> per-company roles via spatie teams, tenant self-service Setup, plans/editions with
> `feature:` gating, a platform console, and subdomain-based tenant branding) shipped across
> several sessions and is tracked separately, not in this file's module table.

---

## Zoho gap-closure queue (2026-08-29 —)

A prioritized list from a Zoho CRM feature-gap comparison, tackled one at a time — each item
built, verified for real (not just structurally), and committed before moving to the next.
Resume at the first item not `DONE`.

| # | Gap | Status | Notes |
|---|-----|--------|-------|
| — | Real SMTP email sending | DONE | See "Post-run additions" above |
| — | Workflow event/schedule triggers | DONE | See "Post-run additions" above |
| — | Real outbound webhooks | DONE | See "Post-run additions" above |
| 1 | Public API — inbound auth (API keys) | DONE | `api_keys` table, `X-Api-Key` header, key resolves to a real `User` so RBAC/scoping/audit all work unchanged. Fixed a real pre-existing bug along the way: `tymon/jwt-auth`'s own package provider registers a `jwt.auth` middleware alias too, and boots *before* the app's provider, so it silently won the alias over `App\Http\Middleware\JwtAuthenticate` — meaning every expired/invalid/missing-token request got raw debug-mode Laravel JSON instead of the app's formatted `{success:false,...}` response, for the entire life of the project until now. Fixed by re-asserting the alias in `AppServiceProvider::boot()` (app providers boot after package providers). Frontend: Settings → API Keys tab (list, create with a one-time plaintext reveal, revoke, delete; "acts as" picks which user's permissions the key inherits). Verified for real: created a key via curl, called `/leads` with only `X-Api-Key` (no JWT) and got the same scoped result set as the JWT session, confirmed a revoked key 401s, confirmed the plaintext is never returned again — then repeated the create/reveal/list-refresh/revoke flow through an actual browser session end to end. |
| 2 | Real payment collection | PARKED | Needs a real Stripe test key from the user (`sk_test_...`) — `stripe-mock` returns canned fixtures, not persisted state, so it can't verify money actually moved. Ask again when picked up. |
| 3 | Sales process enforcement (Blueprint-style guided stages) | DONE | `pipeline_stages.required_fields`/`allowed_next_stage_ids` (json, both null/empty by default — every existing pipeline stays unrestricted). Enforced in `DealService` on every stage-mutating path (`create`, `update`, `moveStage`, `markLost`) via two new private methods, `enforceTransition`/`enforceStageRequirements`, throwing a `RuntimeException` the controllers already turn into a clean 422. Marking a deal Lost always bypasses the transition rule (not the required-fields one) — losing isn't a forward step, and forcing every stage to whitelist Lost would be a footgun. New endpoint `PUT /pipelines/{id}/stages/{id}` (permission `pipelines.manage`, Owner/CEO by default) configures a stage's rules — deliberately narrow, no stage create/delete/rename. Frontend: a "Blueprint" gear icon per Kanban column opens a config modal (required-field checkboxes + allowed-next-stage checkboxes); the manual "move to stage" dropdown now filters to only legal targets; a blocked drag-and-drop gets an instant client-side toast before any round trip. Fixed a real pre-existing bug found while wiring this: `DealController::update()` (`PUT /deals/{id}`) never caught `RuntimeException` from the service layer, so any business-rule rejection on that path (this feature's, but also the pre-existing cross-pipeline check) would have 500'd instead of returning a clean 422 — every sibling endpoint (`store`, `move`) already had this catch. Verified for real via curl: configured Qualification → only Needs analysis, confirmed a direct jump to Proposal 422s with a named-stage message and a legal move succeeds; configured Proposal to require `amount`+`customer_id`, confirmed entering it fails naming exactly the missing field and succeeds once both are set on the deal; confirmed marking a Qualification deal Lost works despite Lost not being in its allowed list; confirmed the `update()` 422 fix directly; confirmed dashboard/stats endpoints are unaffected. Test data and stage config reset afterward. |
| 4 | Customer self-service portal | DONE | A second, fully parallel JWT auth boundary — new `portal` guard + `contacts` Eloquent provider (`config/auth.php`), `Contact` model now `Authenticatable`+`JWTSubject` with its own `password`/`portal_enabled`/`last_login_at` columns. New `PortalAuthenticate` middleware (`portal.auth` alias) — deliberately its own class, not a reuse of `JwtAuthenticate` (that one checks `$user->is_active`, a `User`-only column, and the wrong multi-guard call pattern entirely, see below). Every portal query explicitly filters `withoutGlobalScope('company')` + manual `where('company_id', ...)->where('customer_id', ...)` from the authenticated Contact — deliberately never relies on `BelongsToCompany`'s global scope, because that trait's scope calls the `auth()` **facade** (default guard), which resolves to nothing under a portal-only request and would silently skip filtering rather than throw. New endpoints under `/api/v1/portal/*` (login, me, logout, invoices index/show, tickets index/show/store/reply) plus one staff-side endpoint, `PUT /customers/{id}/contacts/{id}/portal` (permission `customers.update`), for granting/revoking access and setting a password — no email-invite flow (deliberately cut as scope creep for an MVP; a real follow-up if wanted). Portal login is by email alone (no tenant selector), so a contact's email must be unique among portal-enabled contacts *across the whole instance*, enforced at write time in the controller (MySQL has no filtered/partial unique index). Ticket replies/creation reuse `HelpdeskService` (already guard-agnostic for `author_type=customer`); internal notes (`is_internal=true`) are never loaded on the portal side. Frontend: a fully separate `/portal` route tree, `PortalLayout.vue`, `usePortalAuthStore` (own Pinia id, own persisted keys), and a dedicated `portalHttp.js` axios instance — never shares the staff `http.js` instance/interceptors, so a portal page cannot accidentally attach a staff token or vice versa. Found and fixed two real bugs while wiring this: (1) `Auth::guard('portal')->parseToken()->authenticate()` is a trap — `parseToken()` forwards via `__call` to a package-wide singleton `JWT` instance that has no `authenticate()` method (that only exists on the separate `JWTAuth` facade root, itself bound to one fixed guard); the correct per-guard call is `$guard->getPayload()` (throws the granular expired/invalid/missing exceptions) then `$guard->user()` (does the provider-lock check against *that guard's own* provider). (2) `CustomerController::updateContactPortal()` threw "Undefined array key 'password'" when re-enabling portal access without supplying a new password (Laravel's `nullable` validation omits an absent key entirely from `validated()`, so the direct array-access check needed `empty($data['password'] ?? ...)`, not `!$data['password']`). Also made `HelpdeskService::nextTicketNo()`/`create()` accept an explicit `$companyId` (backward-compatible, defaults preserve every existing call site) since the original always read `auth()->user()->company_id`, which is null under portal auth. Verified for real via curl: provider-lock confirmed via decoded JWT `prv` claim (distinct hash per guard/provider); a portal token rejected on every staff route and vice versa; cross-customer IDOR blocked (404, not data leak) on both invoices and tickets using two real customers in the same company; internal ticket notes confirmed hidden from the portal view; login correctly rejected when `portal_enabled=false` and when the linked customer is `blocked`/`archived` (both at login time and mid-session via the middleware); invalid/missing token cases return the right granular errors. Then repeated the full journey (login → invoices → invoice detail → tickets → ticket detail → reply → logout → confirm a protected route bounces back to login) through a real Playwright browser session. Portal requests are **not** audit-logged yet (they sit outside the `jwt.auth`/`scope.company`/`audit` middleware group by design) — noted here as a known gap, not fixed in this pass. Test data (contact, ticket) and modified customer/cache state cleaned up afterward. |
| 5 | Richer analytics/dashboards | DONE | The backend for custom dashboards (`Dashboard` model, `dashboards` CRUD endpoints, `saved_reports`/`report_exports` tables) already existed from the original Reports module build but was **entirely unused by the frontend** — confirmed via curl that it worked end-to-end (including a seeded "Executive overview" dashboard with 4 real widgets) before writing any UI, per the standing lesson that unused code paths in this codebase have a track record of being subtly broken. This item finished it: a new reusable `ReportChart.vue` (Chart.js via `vue-chartjs`, bar/line/pie, multi-measure series) replaces the old div-bar hack in the report builder and powers a new "Dashboards" tab (`DashboardsTab.vue`) — pick/create a dashboard, add/remove saved-report widgets with a size (half/full width), each widget's data fetched independently and in parallel (not sequentially — this dev stack adds real per-request latency). Deliberately cut from scope (see `docs/API.md`): real xlsx/pdf export (still CSV-only — the packages are installed but wiring two new render paths is separate, riskier work) and new report datasets beyond the existing deals/invoices/leads/tickets four. Added light server-side validation to `storeDashboard`/`updateDashboard` that was missing before: `layout.*.report_id` must be a real, non-deleted saved report belonging to the caller's own company (previously any array was accepted; the *render* path was always safely scoped, but a bad ID would have silently produced a permanently broken widget instead of a clean 422 at save time). Found and fixed a real, systemic bug while testing the delete-then-render edge case: `ModelNotFoundException` (from any `findOrFail()` anywhere in the app) and unmatched routes had no explicit JSON renderer in `bootstrap/app.php`, so in production (`APP_DEBUG=false`) they'd fall through to the generic `Throwable` catch-all and wrongly become a flat `500 Server error` instead of a clean `404`. Fixed app-wide, not just for Reports. Verified for real via curl (validation rejects a bad size and a nonexistent report_id; a deleted report now 404s cleanly) and a live Playwright session (bar/line/pie chart switching in the builder, the seeded dashboard rendering all 4 widget types, and the full create → add widget → remove widget → delete dashboard flow) — logged in via direct `localStorage` token injection to avoid repeatedly tripping the login throttle from Playwright reruns. Scratch reports/dashboards cleaned up afterward. |
| 6 | E-signature / quote-to-sign | DONE | Unlike #4/#5, this was genuinely unscaffolded — no signature/signing code, columns, or routes existed anywhere before this. Chose portal-based signing over an anonymous emailed link (which would've been a *third* auth boundary); if the customer lacks portal access, staff grant it via the existing `PUT /customers/{id}/contacts/{id}/portal` from #4. New `quotations.signed_at`/`signed_name`/`signed_ip`/`signature_data` columns; `SalesService::sendQuotation()` (draft→sent, best-effort emails the primary contact a portal link — a mail-server outage never blocks the status change, verified for real via Mailpit both with and without a working SMTP account) and `signQuotation()` (only a `sent` quote is signable; stores a typed name + a drawn canvas signature as a base64 PNG, stamps timestamp/IP, sets `status=accepted`). New portal endpoints (`GET /portal/quotations`, `GET /portal/quotations/{id}`, `POST /portal/quotations/{id}/sign`) follow the #4 scoping pattern exactly — draft quotations are never visible to a portal contact. Gives the two already-declared-but-unused `quotations.approve`/`quotations.send` permissions (seeded to roles since the original Sales module build, never backed by an endpoint) real meaning: `send` now gates the new endpoint. Added `quotations` as a real `Workflow` entity/event (`quotation.sent`, `quotation.signed`) so staff can wire automations (e.g. auto-create a follow-up task on signing) — audited every `auth()->id()` call reachable from a fired action first, since this fires from an unauthenticated-on-the-default-guard portal request; all of them are null-safe and target nullable columns. Frontend: a "Send to customer" button on the quotation detail drawer in Sales (shows a signature preview once signed), and a portal `/portal/quotations` list + detail page with a real from-scratch `SignaturePad.vue` (HTML5 canvas, pointer-event drawing, exports a PNG data URL) — no signature-pad library needed for something this small. Found and fixed two real bugs: (1) `WorkflowService` has a *second*, separate entity-to-model whitelist (`ENTITY_MODELS`, used by `resolveSubject()`) beyond `Workflow::ENTITIES`/`EVENTS` — adding an entity to the model constant without also adding it here fails silently into a caught-and-logged exception, so the workflow event fires but every run immediately errors with "Unknown entity" and does nothing; caught only because a real workflow was configured and its action checked for afterward, not by an API response (the sign endpoint returns 200 either way, since firing a workflow event must never fail the triggering request). (2) The Vite dependency pre-bundle cache went stale for an already-installed-but-newly-exercised package (`Bar`/`Pie` chart.js exports had been added in #5, unrelated to this item) — same fix as always, `rm -rf frontend/node_modules/.vite && docker compose restart frontend`. Verified for real via curl (draft→sent→signed transitions and their 422 guards; cross-customer IDOR blocked on both `show` and `sign`; a draft quotation invisible and un-signable via the portal; oversized `signature_data` rejected; the actual `quotation.signed` workflow firing end-to-end with a real created task once the `ENTITY_MODELS` bug was fixed) and a live two-session Playwright run (a staff browser session sends the quote, a separate portal browser session signs it by drawing a real signature with mouse-drag events, the staff session reloads and sees the signature image and "Accepted" status). All test quotations/workflows/tasks and the temporarily-portal-enabled test contact cleaned up afterward. |
| — | Mobile apps | DEFERRED | User explicitly said "will do later" (2026-08-29) |

### Zoho menu-parity gaps (2026-08-31)

Added from a menu-by-menu comparison against Zoho CRM's *Sales* app — full mapping (including the
✅ Have / ⚠️ Partial items) in [`ZOHO_GAP_ANALYSIS.md`](ZOHO_GAP_ANALYSIS.md). Ordered by fit and
ascending risk; same rule as above — one at a time, built + verified for real + committed.

| # | Gap | Status | Notes |
|---|-----|--------|-------|
| 7 | Price Books | DONE | Per-customer / segment / currency price lists. Scope + decisions in [`PRICE_BOOKS_SCOPE.md`](PRICE_BOOKS_SCOPE.md): **convenience/suggest-only** (line price stays client-editable, `SalesService::syncItems()` untouched), **absolute unit_price** entries, **Professional+** tier. `price_books` + `price_book_entries` tables; nullable `price_book_id` on `customers`/`customer_groups`/`quotations`/`sales_orders`/`invoices`. Batched resolver `POST /price-books/resolve` (explicit book → customer book → group book → list `sale_price`); explicit wrong-currency book = hard 422, implicit mismatch reported + falls to list. `price_books.*` in all three `TenantProvisioner` places. Frontend: Price Books page (list + book form + prices editor), sales line editor pre-fills the suggested price and re-resolves on customer change (still editable), customer form price-book dropdown; en+ar. Verified for real via curl (CRUD, entries sync + bad-product 422, resolver book/list/group-inherited, explicit-422 + non-fatal-implicit mismatch, plan gate) and a live browser session (prices editor pre-filled; a wholesale-group customer auto-filled 3344 vs the 3800 list, and switching to a no-book customer reverted to 3800). Committed in two parts (backend, then frontend). |
| 8 | Solutions / Knowledge Base | DONE | Code name **`kb`** (Zoho "Solutions"); scope in [`KB_SCOPE.md`](KB_SCOPE.md). `kb_categories` + `kb_articles` (**plain-text body**, status draft/published, visibility internal/public, view_count, slug per-company). Staff CRUD `/kb` (`feature:kb`, Professional+); portal read `/portal/kb`. **Double-gate:** portal shows only published **AND** public, company-scoped, **no** customer_id (articles are company-wide — documented departure); atomic `view_count` increment on GET; internal/draft by id → 404. `kb.*` in all three `TenantProvisioner` places (Customer Service full; kb.view to Sales Mgr/Staff). Frontend: staff Knowledge Base page (list + filters + editor), portal Help center (list + article, **pre-wrap plain text, no v-html**), sidebar + portal nav; en+ar. Verified via curl (staff sees 5; portal sees only 3; internal/draft 404; view_count increments; slug dedupe; publish stamps published_at; cross-guard 401; plan gate) and a live browser session (staff list with status/visibility badges; portal help center + article detail; both correctly hiding internal/draft). **Deferred (documented in KB_SCOPE):** ticket↔article linkage, fully-public unauth help center. |
| 9 | Documents | DONE | Staff document library beyond per-record attachments. Scope in [`DOCUMENTS_SCOPE.md`](DOCUMENTS_SCOPE.md): **replace (no version history)**, **flat folders**, **staff-only**, Professional+. `document_folders` + `documents`. **Security — deliberately NOT the attachments pattern** (public disk + url accessor = the world-readable class the audit flagged): files on the **private `local` disk**, hashed filename, company_id in the path but the **DB row's company_id is the auth gate**; **no `url` accessor** — only an authenticated streaming download that resolves the row through the company-scoped model first. **No SoftDeletes**: delete removes row then best-effort unlinks (row first → failed unlink = harmless orphan); replace stores new → repoints row in a txn → deletes old after commit. `documents.*` in all three TenantProvisioner places. Seeder = **folders only** (a root-run seeder writing files would break www-data uploads to the same path — chown gotcha). Frontend: Documents page (folders rail + list + upload + edit/replace/delete), blob download with auth header, sidebar nav; en+ar. Verified via curl as a non-platform user (upload/download bytes-match/replace-removes-old/delete-clears-disk/folders CRUD; **cross-tenant IDOR on a real 2nd company → 404** on metadata and download; plan gate) and a live browser session (page, folder counts, download → 200). **Deferred (in DOCUMENTS_SCOPE):** version history, nested folders, portal sharing, record-linking. |
| 10 | Forecasts | DONE | Quota-based sales forecasting; scope in [`FORECASTS_SCOPE.md`](FORECASTS_SCOPE.md). `sales_targets` (migration 000043): a target per user per period (month|quarter). `ForecastService` compares target vs. **closed-won** deals (`won_at` in period) vs. **open pipeline** (`expected_close_date` in period; gross + probability-weighted), deriving forecast (closed+weighted), attainment%, gap, and a totals roll-up. Aggregates filter `company_id` explicitly (platform-admin bypass — the audit's class). `/forecasts` (feature:forecasts, **Professional+**): board, meta, targets editor (GET), bulk target upsert (PUT, `forecasts.manage`). `forecasts.{view,manage}` (Sales Manager manage, Sales Staff view). Frontend: Forecasts page (month/quarter toggle, board with attainment bars + totals, set-targets editor grid); sidebar nav; en+ar. Verified via tinker (Q3 attainment 18.8% = 141200/750000, forecast = closed+weighted) and a live browser session (month/quarter board, populated attainment bars, targets editor) + curl (permission split view 200 / manage 403; plan gate). Distinct from the AI-prediction heuristic. |
| 11 | SalesInbox — inbound email | DONE | Finishes the Email receive side; scope in [`SALESINBOX_SCOPE.md`](SALESINBOX_SCOPE.md). Owner chose **both** transports. **Ingest core** (`EmailService::ingestInbound`, verified): idempotent by `message_id`; matches sender → Contact→Customer/Customer/Lead (polymorphic `related`); an inbound reply with no match inherits the record it answers (new `emails.in_reply_to`); writes an inbound row + timeline; console-safe. **Transports:** `POST /emails/inbound` (provider-agnostic webhook/manual push — the verifiable path) and an **IMAP fetcher** (`ImapClient` on webklex/php-imap v6 — added cleanly, Carbon-3 OK — + `emails:fetch` scheduled every 10 min + manual `POST /email-accounts/{id}/fetch`), reading IMAP creds from the encrypted `email_accounts.config` (`imapFields`/`isImapConfigured` kept separate from SMTP), pulling UID > `imap_last_uid`. **IMAP can't be run here** (Mailpit is SMTP-only, no IMAP server) — guarded so an unconfigured/unreachable mailbox is a clean no-op, never a fatal ("structure ready" transport). Frontend: IMAP config fields + a "Fetch inbox" button on the account editor; inbound shows in the existing Inbox folder. Verified via curl (match/dedupe/thread/timeline/inbox; IMAP unconfigured + invalid-host → graceful 200 `ok:false`, no 500; secret never returned) and a live browser session (IMAP fields render, Fetch inbox → the graceful message). |
| 12 | Visits — website visitor tracking | DONE | Built in a parallel session (commit `bef3369`; migration 000042; `docs/VISITS_SCOPE.md`). JS tracker + public ingest beacon (204 always, no tenant-enumeration oracle) + identify + retention. `visits.{view,manage}`. |

> **Also open (⚠️ Partial, not queued as full builds):** *Analytics* (Krama has basic dashboards,
> not a full BI product) and *Agents* (Krama's AI Assistant is heuristic, not autonomous Zia-style
> agents) — see `ZOHO_GAP_ANALYSIS.md`. **Pending clarification:** whether Zoho "Agents" meant AI
> agents or human sales/support agents (the latter is already covered by the Users/Roles Settings UI).

### Product management in Inventory — Phase A (2026-08-31, user request)

Separate from the Zoho queue. Product creation now available from **Inventory** (new Products tab),
enriched per the owner's ask. Scope + decisions in [`INVENTORY_PRODUCT_SCOPE.md`](INVENTORY_PRODUCT_SCOPE.md).
Shared `/products` backend (one controller, not duplicated); Sales Products tab kept.
- **Suppliers** — `product_suppliers` (migration 000039): several vendors per product (supplier SKU /
  cost / lead time), one preferred (enforced in `ProductService`). `unique(product_id,vendor_id)`.
- **Opening stock at create** — routes through `InventoryService::receive` (the one row-locked
  ledger writer) in the **same transaction** as the product insert; **skipped for services**.
- **Category hierarchy** — already in the model (`parent_id`); exposed as parent→sub pickers in the
  new form (+ inline category create). No schema change.
- Frontend: `ProductsTab.vue` (list + a 4-section form: Product · Category · Suppliers · Opening
  stock); en+ar. Verified via curl + a live browser create (Ergo desk → preferred supplier stored,
  opening stock 12 wrote one `receipt` ledger move bal 12; service products write no stock;
  cross-company vendor 422; bad-warehouse create rolled back).
- **Phase B — Manufacturing / BOM** ("Production information") — **DONE** (commits: backend +
  frontend; [`MANUFACTURING_BOM_SCOPE.md`](MANUFACTURING_BOM_SCOPE.md)). Decisions: **immediate
  build**, **hard-refuse on short stock**, **Enterprise-only**, headerless BOM. `bom_items`
  (component lines per finished good) + `builds`/`build_items` (migration 000041). A build **locks
  and checks** every component up front, then consumes each and produces the finished good through
  `InventoryService::applyMovement` (the one row-locked ledger writer) in **one transaction** — new
  ledger types `consume`/`produce`; cost rolls up (Σ component avg_cost-or-cost_price × per_unit) to
  the finished good's average_cost. BOM save prevents cycles (self-ref + bounded DFS). New page
  `/app/manufacturing` (Bills of materials + Builds tabs, BOM editor, build modal with live
  availability + shortfall list, build history + detail). `manufacturing.{view,manage,build}` on
  Warehouse; Enterprise-only. Verified via curl + a live browser build (BUILD consumed desk×2/
  chair×2/lamp×4, produced 2 bundles, avg_cost 3810; qty-999 showed shortfalls and disabled Build;
  short-stock/cycle/self-ref → 422 with rollback; plan gate enterprise-only).

---

## Cross-module audit & optimisation pass (2026-08-31, branch `audit/module-sweep`)

A bug/performance sweep across all modules. Method: five parallel module audits plus an
**in-process query-count harness** (boots Laravel, logs in once to respect `throttle:5,1`,
dispatches all 94 parameterless GET endpoints through the HTTP kernel, records status +
SQL count per request, and snapshots every response body so later runs can be diffed).
Repeated identical SQL is the N+1 fingerprint; the body snapshot is the regression net,
since the project still has no test suite. Every fix below was verified for real —
empirically reproduced first, then re-verified — not just reviewed.

### Fixed (6 commits)

| Area | Defect |
|---|---|
| **Tenancy** | `BelongsToCompany`'s scope adds no `company_id` for a platform admin, and four statements relied on it to bound a mass DELETE/UPDATE. `AiPrediction::query()->delete()` compiled to `delete from ai_predictions` with **no WHERE** — `admin@krama.local` is a platform admin *with* a real `company_id`, so "Generate predictions" would wipe every tenant. Same for insights and the dashboard `is_default` flag; the surrounding reads also aggregated across tenants and quoted foreign records back. All scoped explicitly. |
| **Authz** | `GET /search` had **no** `permission:`/`feature:` gate — a Warehouse-role user could read customer names/emails and ticket subjects across five modules. Now gated per table with each module's own permission + plan module. |
| **Authz** | An API key could be minted acting as a **more-privileged user** (keys inherit the target's whole permission set), so `api_keys.create` alone was full tenant takeover. Now restricted to users whose permissions the caller already holds. |
| **Authz** | 4 `exists:users,id` rules missing the company scope 10+ siblings have → cross-tenant user enumeration via the echoed owner/assignee name. |
| **Perf** | `deals/board` eager-loaded relations *inside* the per-stage loop: **25 → 15** queries, no longer growing with stage count. |
| **Perf** | Users list issued one roles query per row: **16 → 8**; 100+ extra at `per_page=100`. |
| **Perf** | `listReminders()` never eager-loaded the polymorphic `related` (the only activities list that didn't) — 1 query per reminder. |
| **Perf** | `deals/stats` + AI forecast hydrated every open deal to sum in PHP → SQL `SUM(ROUND(...))`, proven numerically identical. |
| **Perf** | Dashboard `limit` was unclamped and the query builder *ignores a negative limit*, so `?limit=-1` removed the LIMIT entirely. Clamped 1..100. `topPerformers` also omitted `$limit` from its cache key. |
| **HR** | **Every attendance record stored `hours_worked = 0`.** Carbon 3 (pinned 3.13.2) made `floatDiffInHours` signed; `$out->floatDiffInHours($in)` returns −8.0 for a 09:00–17:00 shift, which the existing `max(...,0)` flattened to 0. |
| **Activities** | `status=open` excluded `in_progress` — two `when()` clauses both fired and the second could only narrow, so `Task::scopeOpen` was dead code and the list disagreed with the dashboard tile. |
| **Helpdesk** | `escalate()` never recomputed the SLA: `save()` calls `syncOriginal()`, so the guard compared the new priority with itself and the branch was dead. **Now live, with a consequence to be aware of:** `applySla()` bases deadlines on the ticket's `created_at`, so escalating an older ticket tightens `due_at` against a clock that has already run. Measured on a real ticket: `high → urgent` moved `due_at` from 19:37 to 15:37 the same day, i.e. further into the past, so it reads as breaching and feeds `stats()['breaching']` and the SLA KPI. Correct by that policy's own clock; if escalation should instead restart the clock, `applySla()` needs a base-time argument. |
| **Sales** | `paginatePayments` `q` filter had an **ungrouped `orWhere`** — `?q=X&customer_id=7` returned the whole company's matching payments. Only such site in `app/`. |
| **Inventory** | `StockItemResource` 500'd the entire stock list once any product was soft-deleted (`whenLoaded` is true for a relation that resolved to null). |
| **Errors** | `markLost` / `reports export` were the lone call sites in their modules without `catch (RuntimeException)` → 500 instead of 422. |
| **Errors** | `QueryException extends PDOException extends RuntimeException`, so all **30** business-rule catches across 15 controllers also caught DB failures and returned the **raw SQL, bindings and index name** as a 422. Guarded at every site. |
| **Seeder** | `AiSeeder` used `withoutGlobalScopes()` (plural — strips `SoftDeletingScope` too), so seeded figures counted deleted records: forecast read 484930 vs a true 479660. |

### Known-good (checked, no action)
No SQL injection in the report dataset registry (every column/table/aggregate is
registry-derived; full path traced). Portal IDOR clean — all four portal controllers filter
both `company_id` and `customer_id`. Secrets clean in all Resources. Platform console,
API-key revocation/expiry, and mass assignment all clean.

### Follow-up pass (same branch): the two headline security items

**2FA now actually enforced.** It was decorative for two independent reasons: the `2fa`
alias was applied to *zero* routes, and `RequireTwoFactor` read `session('2fa_verified')`
on a stateless JWT API with no session middleware — so had it ever been wired it would
have failed closed on every request instead of honouring a verify. Verification state now
lives in the token as a `twofa` claim: login mints `pending` for a 2FA-enabled user,
`POST /auth/2fa/verify` checks the TOTP and exchanges it for `ok`. A claim rather than a
server-side marker is deliberate — **a pending token can never become verified**, so one
captured before verification stays useless. Applied to the whole authenticated group
(no-op for users without 2FA); `2fa/verify`, `logout`, `me`, `refresh` opt out via
`withoutMiddleware` so a pending token can finish or abandon the challenge.
`change-password` stays gated on purpose (it takes only the current password).
`X-Api-Key` bypasses — the key *is* the credential and there is no token to carry a claim.
`2fa/confirm` also returns a verified token so enabling 2FA doesn't lock you out.
**Test note:** this must be verified over real HTTP — dispatching many requests through one
in-process kernel lets the shared `tymon.jwt` singleton cache token state between them and
report false passes/failures.

**Report exports are private.** They were written to `storage/app/public` under
`report-{reportId}-{exportId}.csv` with both ids global auto-increments, served through the
`public/storage` symlink. Confirmed live before fixing: an unauthenticated GET returned
HTTP 200 and real data. Now on the private `local` disk keyed by `company_id`, reachable
only via `GET /reports/exports/{id}/download` (`permission:reports.export`), authorised by
resolving through the company-scoped model — same shape as Documents. Migration `000037`
moves existing files (copy → verify → repoint row → then unlink, so a partial run leaves a
readable duplicate rather than a dangling row) and clears the public tree.
**Two traps:** the migration creates dirs under `storage/app/private`, and run as root
(which `docker compose exec` does) they are root-owned mode 700 and PHP-FPM then cannot
write new exports — run `chown -R www-data:www-data storage/app/private` after migrating.
And `.gitignore` covered `/storage/app/public/*` but **not** `/storage/app/private/*`,
where Laravel 11+ roots the local disk; rule added, since Documents writes there too.

**Customer credits (credit notes) — overpayment no longer vanishes.** `recalcInvoice` stored
`balance = max(grand_total - paid, 0)`, so a 1000 payment on a 100 invoice recorded
`amount_paid = 1000`, `balance = 0.00` and counted 1000 toward `collected_mtd` with nothing
recording the 900 owed back; reducing an invoice below what was already paid hid the
difference the same way. Built the full credit-note concept (user's choice over
reject/negative-balance): `customer_credits` + `credit_applications`, plus
**`payments.applied_amount`** — `amount` stays the cash received (so `collected_mtd` and the
dashboard cash series stay true) while `applied_amount` is the portion that settles the
invoice. That split is what makes the derivation immune to a later invoice edit; capping
`amount_paid` instead would double-count the excess if the invoice were edited upward.
Applying a credit is deliberately **not** a `payments` row — it would double-count the
original overpayment in `collected_mtd`. Credits carry `source_payment_id` so
`deletePayment` can reverse them (unapplied → voided with the payment; already applied →
422, or another invoice would stay settled with money that no longer exists). `apply()`
locks the credit row before reading `remaining`, so it does not join the check-then-act
family below. Plan tier **Starter**, alongside invoices/payments: the system mints credits
on overpayment on every plan, so gating the module out of a tier would leave those tenants
with money recorded and no way to see or spend it. Verified with 30 numeric assertions —
including that `collected_mtd` rises by the full cash on overpayment and is **unchanged**
when a credit is applied. Out of scope by design: cash refunds, auto-apply at invoice
creation, cross-currency application.

### Outstanding from the audit: none — all cleared

The last four findings were fixed in three commits after customer credits:

- **Leave balances failing every Jan 1.** `balanceFor()` keyed on `now()->year` while
  `ensureBalances()` ran only at employee creation, so from Jan 1 of year N+1 it returned
  null and both callers treated that as "no constraint" — the check AND the deduction were
  skipped, making leave unlimited and untracked. The year now comes from the request's
  `start_date`. That also fixed an unlisted second bug: a request approved in one year and
  cancelled in the next credited the **wrong** year — and a naive self-healing fix would
  have made it worse by creating that row and driving `used` negative (signed column, so
  MySQL wouldn't stop it), so the cancel path deliberately does **not** create a row.
  Also in the same cluster: `approveRequest` never re-checked the balance (two requests each
  passing at filing time could both be approved past `entitled`, no concurrency needed), and
  approve/cancel read status outside their transaction with no lock. `findEmployee()` now
  self-heals so the UI doesn't show empty balances.
- **Portal tickets got no SLA.** `Ticket::create()` doesn't hydrate DB defaults, so a request
  omitting `priority` — every portal ticket — left it NULL in memory; `applySla` matched
  `whereNull('priority')` against a NOT NULL column and wrote `due_at` as NULL permanently,
  keeping the ticket out of the breaching filter, the stats and the SLA KPI. One `refresh()`
  before `applySla` fixes that **and** the `"Ticket created via ."` reply body. The policy
  lookup is now scoped by `company_id` explicitly — it's reachable on the `portal` guard,
  where the global scope adds nothing, so it had been selecting other tenants' policies.
- **Workflow `update_field` corrupting derived state.** `deals.stage_id` now delegates to
  `DealService::moveStage` (blueprint, `pipeline_id`, derived status, won_at/lost_at,
  timeline) and `tickets.priority` to `HelpdeskService::update` (SLA recompute).
  `deals.status` and `invoices.status` were **removed** from `UPDATABLE` — both are derived,
  and writing them directly is what made a deal show Won on the Kanban while `Deal::open()`
  still counted it. Delegation made cascades possible for the first time, so a re-entrancy
  guard was added: a workflow's own actions never trigger further workflows.
- **Workflow conditions ignored with no subject.** The guard read `if ($subject && !pass())`,
  so every schedule trigger ran its actions unconditionally while the UI displayed the
  conditions as live. Now fails closed with a skipped run explaining why.
- **The check-then-act races.** `applyMovement` (stock `balance_after`), `adjust`
  (`mode=set`), `receiveOrder` (`received_quantity`), `cancelTransfer` (movements committed
  outside the status flip → retry double-posted stock) and `actOnApproval` (approval
  committed, document status separate → PO permanently stuck) all now lock and/or run in one
  transaction. Proved with **real** concurrency: 10 simultaneous issues through nginx left
  on-hand exactly correct with 10 distinct `balance_after` values.
  **`nextXxxNo()` left unlocked deliberately** — a collision hits a unique index and fails
  cleanly rather than corrupting, and the pattern is uniform across every document type.

---

## Already built (before this run)

| Module | Status | Notes |
|---|---|---|
| Foundation | DONE | companies, branches, departments, users, RBAC, system tables |
| M1 Dashboard | DONE | DashboardService + widgets, real KPI endpoints |
| M16 Social/Chat Inbox | DONE | 6 tables, multi-account, media, TikTok |
| M17 Dynamics 365 BC | **PULL DONE** | 6 tables, client/engine/API/UI + 4 syncers: `company`, `customer`, `product` (BC `items`), `invoice` (**reconcile-only**). Pull only — push deliberately not built. Scope: [`DYNAMICS_BC_SYNC_SCOPE.md`](DYNAMICS_BC_SYNC_SCOPE.md). **Still never run against a live BC tenant** — verified against a local OData stub, which proves our sync logic and cannot prove BC's real field names, endpoints or auth. |

---

## Build queue

| # | Module | Status | Key tables |
|---|--------|--------|-----------|
| 1 | M3 Customers | **DONE** | customers, contacts, customer_groups, **addresses**, **timeline_activities** |
| 2 | M2 Leads | **DONE** | leads, lead_sources, lead_statuses, lead_assignment_rules, **attachments** (shared). Notes reuse `timeline_activities`; no `lead_notes`/`lead_attachments`. |
| 3 | M4 Pipeline | **DONE** | pipelines, pipeline_stages (**order_index**, is_won/is_lost), deals (stage-derived status), deal_products (**product_id FK deferred to M7**), lost_reasons. Kanban board with drag-move. |
| 4 | M5 Activities | **DONE** | tasks (dashboard contract), meetings, meeting_participants, calls, operational reminders. Polymorphic `related` → deal/lead/customer/contact/quotation; unified feed and scheduled notifications. |
| 5 | M7 Sales | **DONE** | **products**, product_categories, **tax_rates**, quotations, sales_orders, invoices, payments (+items). Server-side tax/totals; quote→order→invoice→pay chain; closed `deal_products.product_id` FK. |
| 6 | M9 Inventory | **DONE** | warehouses, stock_items, stock_movements (signed ledger + balance_after), stock_transfers (+items, ship/receive moves stock), barcodes. Adjust/receive/issue funnel through `applyMovement`. |
| 7 | M8 Purchase | **DONE** | vendors, purchase_requests (+items), purchase_orders (+items, receive posts stock), **approval_workflows** (+requests/actions, generic multi-step engine). |
| 8 | M10 Helpdesk | **DONE** | tickets (dashboard contract status+due_at), ticket_categories, ticket_replies (public/internal), sla_policies (per priority, auto deadlines), escalations. SLA breach feeds the KPI. |
| 9 | M6 Email | **DONE** | email_accounts, email_templates, emails (direction/status/opens/clicks, polymorphic related), email_attachments. Compose/send structural; links write timeline. |
| 10 | M11 Marketing | **DONE** | campaigns (email/sms, audience JSON), campaign_recipients (materialised), campaign_messages, sms_providers. Launch = structural send. CEO/admin only. |
| 11 | M12 HR | **DONE** | employees, attendances, leave_types, leave_requests, leave_balances. Leave flow: request checks balance, approve deducts + stamps attendance. |
| 12 | Settings UI | **DONE** | no new tables — admin CRUD for company profile, branches, departments, users (+role assignment), roles (+permission editor). Guards: no self-delete, Super Admin role protected. |
| 13 | M13 Reports | **DONE** | saved_reports, dashboards, report_exports. Whitelisted dataset registry (deals/invoices/leads/tickets); grouped query runner; CSV export writes a real file. |
| 14 | M14 Workflow | **DONE** | workflows (trigger+conditions), workflow_actions (ordered), workflow_runs (logged), scheduled_jobs. Engine: conditions gate, real create_task/update_field/send_email actions, run history. |
| 15 | M15 AI Assistant | **DONE** | ai_conversations, ai_messages, ai_insights, ai_predictions. Heuristic chat over real data; insights/predictions generated; wires the dashboard ai-insights widget. |
| 16 | Project Management foundation | **DONE** | projects, project_members, project_milestones, project_tasks. Full tenant-scoped CRUD, progress roll-up, team allocation, and won Deal → Project handoff. Dedicated Projects workspace. |
| 17 | Project Management collaboration | **DONE** | Full task editor, list/Kanban, subtasks, cycle-safe dependencies, prerequisite enforcement, comments, shared attachments, and human-readable activity history. |
| 18 | Project time, cost & capacity | **DONE** | Member cost/bill rates, time submission/approval, immutable approved entries, task actual-hours roll-up, financial summary, team workload/capacity, and idempotent due/overdue notifications. |
| 19 | Project templates, recurrence, automation & reporting | **DONE** | Save tenant-owned project blueprints and instantiate them with relative dates; daily/weekly/monthly recurring tasks; idempotent event rules for notification, follow-up creation, and priority escalation; management health and currency-aware financial reporting. |
| 20 | Social Inbox → CRM identity handoff | **DONE** | Email/phone match suggestions across Leads, Contacts, and Accounts; tenant-safe link/unlink; Lead creation from a conversation with source attribution, duplicate refusal, owner handoff, link history, and direct CRM navigation. |

---

## Definition of done, per module

1. Migration (next number in `backend/database/migrations/`)
2. Models with `BelongsToCompany` + casts + relations
3. Service layer (business logic, transactions)
4. FormRequests for validation
5. Controller + routes under `/api/v1`, each gated by a `permission:` middleware
6. API Resources (whitelist fields; never leak secrets)
7. Permissions added to `RolePermissionSeeder` **and to the relevant roles**
8. Demo seeder (must pass `company_id` explicitly — `BelongsToCompany` does not fire unauthenticated)
9. Vue page replacing the `ComingSoon` stub + route + sidebar nav
10. Locale strings in **both** `en.json` and `ar.json`
11. Docs updated: `ERD.md`, `API.md`, `ARCHITECTURE.md`, `README.md`
12. Verified with `curl` before touching the UI, then verified in the browser
13. Committed

---

## Standing gotchas (learned the hard way in this project)

- **`migrate`, never `migrate:fresh`** — seeders are idempotent; fresh destroys data.
- **Log out / back in after adding permissions.** They are baked into the auth store at login
  and persisted to localStorage; the router then *silently* redirects to dashboard with no error.
- **Normal frontend source edits use Vite HMR** over the bind mount; do not restart the
  frontend container for ordinary `.vue`/`.js`/locale changes because that interrupts open
  browser sessions. Restart only after dependency or Vite configuration changes.
- **Synthetic typing does not trigger Vue `v-model`.** Set values via the native setter +
  `input` event, and remember Vue's DOM update is async — check `disabled` on a later call.
- **`docker compose exec` runs as root.** Anything it creates under `storage/` must be
  `chown -R www-data:www-data` or PHP-FPM cannot write to it.
- **New queues must be added to the worker command** in `docker-compose.yml`, or jobs sit
  unprocessed forever with no error.
- **Never commit** `.env`, `vendor/`, `node_modules/`, DB dumps, or `storage/app/public/*`.

---

## Unblocked as we go

- **M17 BC syncers: DONE (pull).** `CustomerSyncer`, `ItemSyncer` and a reconcile-only
  `InvoiceSyncer` all shipped once their modules existed — see the M17 row above and
  [`DYNAMICS_BC_SYNC_SCOPE.md`](DYNAMICS_BC_SYNC_SCOPE.md). Push remains unbuilt by decision.
- **M16 chat:** `chat_contacts.linked_type/linked_id` is deliberately unconstrained and can
  link real Leads/Customers after steps 1–2.
- **M1 Dashboard:** all widgets are now live — pipeline (step 3), revenue / sales-chart /
  top-performer (step 5), tasks (step 4), purchase chart (step 7). No placeholder data remains.
