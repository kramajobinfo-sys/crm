# Krama CRM — API Reference (v1)

Base URL: `http://localhost:8000/api/v1`
All responses are JSON with the envelope:
```json
{ "success": true, "message": "OK", "data": {...}, "meta": {...} }
```
Auth: send `Authorization: Bearer <token>` on protected routes. Third-party integrations can
instead send `X-Api-Key: <key>` (see "API Keys" below) — same routes, same permission/feature
gates, no login flow needed.

`GET /my-work` returns the current user's assigned CRM and Project tasks as one source-labelled
queue. Filters: `q`, `source=all|crm|project`, and `state=open|completed|all`.

Most module route groups are gated by two independent checks: an RBAC `permission:` (is this
*user's role* allowed) and a `feature:{module}` (does this *tenant's plan* include the module — see
"Editions" in ARCHITECTURE.md). A feature block returns **403** with
`{"success":false,"message":"The \"<module>\" module isn't included in your plan."}`.

## Auth
| Method | Endpoint                | Auth | Body / Notes |
|--------|-------------------------|------|--------------|
| POST   | /auth/login             | No   | `{email, password, remember?}` → returns access_token + user |
| POST   | /auth/register          | No   | `{company_name, name, email, password, password_confirmation}` — creates a new company (tenant) on the **Starter** plan with an auto-generated unique `subdomain` slug + its first user (**Owner** role, never a platform admin), auto-logs in. Throttled 5/min |
| POST   | /auth/refresh           | Yes  | rotates token |
| POST   | /auth/logout            | Yes  | blacklists token |
| GET    | /auth/me                | Yes  | current user + roles + permissions |
| PUT    | /auth/profile           | Yes  | `{name?, phone?, language?, timezone?}` — update own profile |
| POST   | /auth/avatar            | Yes  | multipart `avatar` (image, ≤4 MB) — update own photo |
| POST   | /auth/change-password   | Yes  | `{current_password, new_password, new_password_confirmation}` |
| POST   | /auth/forgot-password   | No   | `{email}` |
| POST   | /auth/reset-password    | No   | `{token, email, password, password_confirmation}` |

### 2FA
| POST | /auth/2fa/enable  | Yes | returns secret + QR url |
| POST | /auth/2fa/confirm | Yes | `{code}` |
| POST | /auth/2fa/verify  | Yes | `{code}` |
| POST | /auth/2fa/disable | Yes | `{password}` |

## Dashboard (Module 1)
| GET | /dashboard/summary         | all widgets in one call |
| GET | /dashboard/kpis            | revenue, leads, deals, tickets |
| GET | /dashboard/sales-chart?months=7 | monthly sales series |
| GET | /dashboard/purchase-chart?months=7 | monthly purchase series |
| GET | /dashboard/revenue-chart?months=12 | monthly payments |
| GET | /dashboard/pipeline        | deals grouped by stage |
| GET | /dashboard/top-performers?limit=5 | leaderboard |
| GET | /dashboard/tasks-summary   | current user's tasks |
| GET | /dashboard/recent-activity?limit=10 | audit feed |
| GET | /dashboard/ai-insights     | AI insights (Module 15 placeholder) |

## Leads (Module 2)
| Method | Endpoint | Permission | Notes |
|--------|----------|-----------|-------|
| GET    | /leads | leads.view | filters: `q`, `converted` (all/open/converted), `status_id`, `source_id`, `rating`, `owner_id`, `per_page`. Sorted by score desc. |
| GET    | /leads/stats | leads.view | open / converted / hot / warm / cold / mine / unassigned / pipeline_value |
| GET    | /leads/meta | leads.view | sources, statuses, ratings, next `lead_no` |
| GET    | /leads/{id} | leads.view | with score breakdown, addresses, attachments, timeline |
| POST   | /leads | leads.create | omit `owner_id` and the assignment rules pick one |
| PUT    | /leads/{id} | leads.update | |
| DELETE | /leads/{id} | leads.delete | soft delete |
| GET    | /leads/{id}/score | leads.score | score + rating + component breakdown, without persisting |
| POST   | /leads/{id}/notes | leads.update | `{body, type?}`; a call/email/meeting also stamps `last_contacted_at`, which moves the score |
| POST   | /leads/{id}/assign | leads.assign | `{user_id}` to set directly, or send `{}` to run the rules |
| POST   | /leads/{id}/convert | leads.convert | converts into a new/existing Account, optional Contact and optional Deal; **422** if already converted |
| POST   | /leads/{id}/attachments | leads.update | multipart `file`, 15 MB cap, mime allowlist |
| DELETE | /leads/{id}/attachments/{attId} | leads.update | |

Lead conversion accepts `account_mode` (`new` or `existing`), either `account{}` or `account_id`,
`create_contact` with optional `contact{}`, and `create_deal` with optional `deal{}`. The Account,
Contact, Deal, Deal Contact role, Lead status, and timelines are written in one transaction. A
failure rolls the entire conversion back. Existing Account selections are tenant validated.

**Conversion** maps the *company* to the customer and the *person* to a primary contact,
carries addresses over, moves the lead to a `is_won` status, and writes a timeline entry on
both records. It refuses a second conversion rather than creating a duplicate account.

## Accounts (Module 3; backend resource name `customers`)
| Method | Endpoint | Permission | Notes |
|--------|----------|-----------|-------|
| GET    | /customers | customers.view | filters: `status`, `type`, `group_id`, `owner_id` (`me`/id), `q`, `per_page` |
| GET    | /customers/stats | customers.view | total / active / on_hold / blocked / mine / new_this_month |
| GET    | /customers/meta | customers.view | groups, types, statuses, next `customer_no` |
| GET    | /customers/{id} | customers.view | with contacts, addresses and last 50 timeline entries |
| POST   | /customers | customers.create | `customer_no` optional — generated when omitted |
| PUT    | /customers/{id} | customers.update | sending `addresses` **replaces** the whole set; omitting it leaves them untouched |
| DELETE | /customers/{id} | customers.delete | soft delete |
| POST   | /customers/{id}/notes | customers.update | `{body, type?}` — note\|call\|email\|meeting |
| POST   | /customers/{id}/contacts | customers.update | setting `is_primary` demotes the others |
| PUT    | /customers/{id}/contacts/{contactId} | customers.update | |
| DELETE | /customers/{id}/contacts/{contactId} | customers.update | |
| PUT    | /customers/{id}/contacts/{contactId}/portal | customers.update | `{portal_enabled, password?}` — grants/revokes customer-portal login for this contact; see "Customer portal" below |

A status change is recorded automatically as a `status_change` timeline entry.
Permissions are granular: Sales Staff can view and update but **not** delete.

## Contacts (standalone CRM module)

Contacts remain stored in the existing `contacts` table and remain accessible under their
Account. These endpoints add an independently searchable module for people without duplicating
the nested Account CRUD.

| Method | Endpoint | Permission | Notes |
|---|---|---|---|
| GET | /contacts | contacts.view | filters: `q`, `customer_id`, `primary` (`all`/`yes`), `per_page` |
| GET | /contacts/meta | contacts.view | active/non-archived Account lookup list |
| GET | /contacts/{id} | contacts.view | Contact with Account and addresses |
| POST | /contacts | contacts.create | `customer_id` is required and tenant-validated |
| PUT | /contacts/{id} | contacts.update | may move the Contact to another Account in the same tenant |
| DELETE | /contacts/{id} | contacts.delete | soft delete |

Setting `is_primary=true` demotes every other primary Contact for the selected Account. Every
record lookup and Account assignment is tenant-scoped; cross-tenant IDs return 404 or validation
errors without revealing the foreign record.

## Duplicate detection

| Method | Endpoint | Permission | Notes |
|---|---|---|---|
| POST | /duplicates/check | Depends on `type` | Advisory exact-match check for `lead`, `account`, or `contact`; accepts `exclude_id` while editing. |
| POST | /duplicates/merge-preview | Update + delete for type | `{type, primary_id, duplicate_id}` returns conflicts and relationship counts without changing data. |
| POST | /duplicates/merge | Update + delete for type | Adds `field_sources` (`primary` or `duplicate` per field); transactional and audit logged. |

The endpoint is always tenant-scoped and never merges or changes data. It checks email, phone,
company/Account name, tax ID, and Contact name within an Account where applicable, returning up
to 10 candidates with match reasons and a confidence label. Permissions are `leads.view`,
`customers.view`, or `contacts.view` according to the requested type. The UI lets users review a
warning or intentionally save a separate record.

Merge keeps `primary_id`, soft-deletes `duplicate_id`, and moves related timelines, addresses,
attachments, Deals and visits. Account merge also moves Contacts, sales documents, payments,
tickets and credits. Contact merge consolidates Deal contact roles and requires both Contacts to
belong to the same Account. All merge operations lock both records, reject cross-tenant IDs, and
write an explicit `merged` audit event with field choices and relationship counts.

## Deals / Pipeline (Module 4)
| Method | Endpoint | Permission | Notes |
|--------|----------|-----------|-------|
| GET    | /deals | deals.view | filters: `q`, `pipeline_id`, `stage_id`, `status` (all/open/won/lost), `customer_id`, `owner_id` (`me`/id), `per_page` |
| GET    | /deals/board | deals.view | kanban: every stage of a pipeline with its deals, count and total. `pipeline_id` optional (defaults to the default pipeline), `owner_id` |
| GET    | /deals/stats | deals.view | open / won (MTD) / lost / mine / open_value / weighted_value / win_rate |
| GET    | /deals/meta | deals.view | pipelines (with stages), lost_reasons, statuses, next `deal_no` |
| GET    | /deals/{id} | deals.view | with products, timeline, attachments, lost reason |
| POST   | /deals | deals.create | `deal_no` optional; starts in the given `stage_id` or the pipeline's first stage; optional `products[]` set the amount |
| PUT    | /deals/{id} | deals.update | sending `products` **replaces** the line set and rolls the total into `amount` |
| DELETE | /deals/{id} | deals.delete | soft delete |
| POST   | /deals/{id}/move | deals.change_stage | `{stage_id}`; **422** if the stage is in another pipeline. Drives status + won/lost stamps |
| POST   | /deals/{id}/lost | deals.change_stage | `{lost_reason_id?, note?}`; moves to the lost stage and marks the deal lost |
| POST   | /deals/{id}/notes | deals.update | `{body, type?}` — note\|call\|email\|meeting |
| POST   | /deals/{id}/attachments | deals.update | multipart `file`, 15 MB cap, mime allowlist |
| DELETE | /deals/{id}/attachments/{attId} | deals.update | |

Deal create/update accepts an optional `contacts[]` array. Each row contains `contact_id`, `role`
(`decision_maker`, `champion`, `influencer`, `evaluator`, `billing`, or `other`) and optional
`is_primary`. Sending the array replaces the Deal's contact-role set. Contacts must be active,
belong to the current tenant, and belong to the selected Account; at most one may be primary.
Changing a Deal's Account without sending `contacts` safely removes the old Account's roles.

**Stage is authoritative for won/lost.** Moving a deal to an `is_won`/`is_lost` stage sets
`status`, stamps `won_at`/`lost_at`, and forces probability to 100/0. This keeps the dashboard
KPI (which excludes won/lost stages) and the board in agreement.

### Blueprint (guided sales process)
| Method | Endpoint | Permission | Notes |
|--------|----------|-----------|-------|
| PUT | /pipelines/{pipelineId}/stages/{stageId} | pipelines.manage | `{required_fields?: string[], allowed_next_stage_ids?: int[]}` — either or both `null`/absent clears that rule |

A stage can require certain deal fields be filled before a deal enters it (`required_fields`, one
or more of `amount`, `customer_id`, `owner_id`, `expected_close_date`, `probability`,
`lost_reason_id` — see `Deal::BLUEPRINT_FIELDS`), and can restrict which stages it may move to
next (`allowed_next_stage_ids`). Both are enforced in `DealService` on every path that changes a
deal's stage — `/deals/{id}/move`, `PUT /deals/{id}`, and `/deals/{id}/lost` — returning **422**
with a message naming the missing fields or the blocked transition. Both rules are `null`/empty by
default, so an unconfigured pipeline stays fully open exactly as before this feature existed.
**Marking a deal Lost always bypasses the transition restriction** (though not the required-fields
one) — losing isn't a forward process step, and requiring every stage to explicitly whitelist the
Lost stage would be an easy way to accidentally brick `markLost()`. There is no stage
create/delete/rename endpoint — stages remain seeder-only; this is configuration of Blueprint
rules on existing stages, not a stage lifecycle API.

## Activities (Module 5)
All endpoints under `/activities`, gated by `activities.*`. The `related` link accepts a short
alias `{related_type: deal|lead|customer|contact|quotation, related_id}`. The related ID is
validated within the authenticated tenant before the activity is created or updated.

| Method | Endpoint | Permission | Notes |
|--------|----------|-----------|-------|
| GET  | /activities/feed  | activities.view | unified upcoming feed (open tasks + scheduled meetings/calls), `scope=me\|all` |
| GET  | /activities/stats | activities.view | my_open_tasks / overdue / due_today / upcoming_meetings / calls_logged / completed_this_week |
| GET  | /activities/meta  | activities.view | status/priority/direction enums + related types |
| GET/POST | /activities/tasks | activities.view / .create | filters: `q`, `status`, `priority`, `assigned_to` (`me`/id), `due` (overdue/today) |
| PUT/DELETE | /activities/tasks/{id} | activities.update / .delete | |
| POST | /activities/tasks/{id}/complete | activities.update | stamps `completed_at` |
| GET/POST | /activities/meetings | activities.view / .create | `participants[]` (user or external name/email); filters `when` (upcoming/past) |
| PUT/DELETE | /activities/meetings/{id} | activities.update / .delete | sending `participants` **replaces** the set |
| GET/POST | /activities/calls | activities.view / .create | a `completed` call with no time defaults `occurred_at` to now |
| PUT/DELETE | /activities/calls/{id} | activities.update / .delete | |
| GET/POST | /activities/reminders | activities.view / .create | lists/creates the caller's unsent reminders; create accepts `title`, `remind_at`, `channel=in_app|email`, and optional tenant-validated related link |
| POST | /activities/reminders/{id}/complete | activities.update | marks sent/dismissed |
| DELETE | /activities/reminders/{id} | activities.delete | |

The scheduler runs `activities:dispatch-reminders` every minute. Each due reminder is claimed
once under a database lock and creates an in-app notification. `email` also attempts delivery
asynchronously through that reminder company's configured SMTP account, so slow SMTP cannot
block the scheduler. Delivery failures remain recorded in the Email module while the in-app
notification remains available.

## Sales (Module 7)
Documents (quotations/orders/invoices) accept `items[]` `{product_id?, name, quantity,
unit_price, discount_pct, tax_rate_id?}`; totals and tax compute server-side.

| Method | Endpoint | Permission | Notes |
|--------|----------|-----------|-------|
| GET/POST | /products | products.view / .create | filters: `q`, `category_id`, `type`, `status` |
| GET | /products/stats, /products/meta | products.view | meta = categories, tax_rates, types, next SKU |
| PUT/DELETE | /products/{id} | products.update / .delete | soft delete |
| POST | /products/categories, /products/tax-rates | products.create | one default tax rate per company |
| GET/POST | /price-books | price_books.view / .create | Zoho gap #7; `feature:price_books` (Professional+). Filters: `q`, `currency`, `status` |
| GET | /price-books/meta | price_books.view | currencies for the create form |
| GET/PUT/DELETE | /price-books/{id} | price_books.view/.update/.delete | GET returns entries with product + list price |
| PUT | /price-books/{id}/entries | price_books.update | replaces the whole entry set; `entries[]` `{product_id, unit_price}` (absolute) |
| POST | /price-books/resolve | price_books.view | **convenience/suggest-only**; body `{price_book_id?, customer_id?, currency?, product_ids[]}` → `{price_book, prices{id:price}, source{id:'price_book'|'list'}, currency_mismatch}`. Precedence: explicit book → customer book → group book → `products.sale_price`. Explicit wrong-currency book = **422**; implicit mismatch is reported and falls through to list (never silent). Read path only — the quote/order/invoice write paths are unchanged |
| GET/POST | /quotations | quotations.view / .create | |
| GET/PUT/DELETE | /quotations/{id} | quotations.view/.update/.delete | sending `items` replaces the set |
| POST | /quotations/{id}/status | quotations.update | draft/sent/accepted/rejected/expired |
| POST | /quotations/{id}/send | quotations.send | draft → sent; **422** if not currently draft. Emails the customer's primary contact a link to the portal — best-effort: a mail failure never blocks the status change, see `{emailed: bool}` in the response |
| POST | /quotations/{id}/convert | orders.create | → new draft sales order; **422** if already converted |
| GET/POST | /sales-orders | orders.view / .create | |
| GET/PUT/DELETE | /sales-orders/{id} | orders.* | |
| POST | /sales-orders/{id}/status | orders.update | draft/confirmed/processing/fulfilled/cancelled |
| POST | /sales-orders/{id}/convert | invoices.create | → new issued invoice; **422** if already invoiced |
| GET/POST | /invoices | invoices.view / .create | `stats` = revenue/outstanding/collected etc. |
| GET/PUT/DELETE | /invoices/{id} | invoices.* | |
| POST | /invoices/{id}/status | invoices.update | draft/issued/void (money states derive from payments) |
| POST | /invoices/{id}/pay | payments.create | `{amount, method?, reference?}`; re-derives status/balance. **Overpayment is not rejected and not discarded**: the full amount is recorded as cash, only the outstanding portion is applied to the invoice, and the excess becomes an open customer credit (returned as `credit` on the payment) |
| GET | /payments | payments.view | filters: `q`, `method`, `customer_id` |
| DELETE | /payments/{id} | payments.delete | recalculates the linked invoice. **422** if the payment's overpayment credit has already been applied elsewhere — otherwise that invoice would stay settled with money that no longer exists |
| GET | /invoices/{id}/available-credits | credits.view | credits with something left for this invoice's customer **and currency** — the picker can only offer what the server would accept |
| POST | /invoices/{id}/apply-credit | credits.apply | `{credit_id, amount?}`; omit `amount` to apply as much as both sides allow. 422 on: over-remaining, cross-customer, cross-currency, voided credit, already-settled or draft/void invoice |
| GET | /customer-credits | credits.view | `feature:credits` (Starter+ — the system mints credits on every plan). Filters: `q`, `customer_id`, `source`, `status` (`available` = open with a remainder) |
| GET | /customer-credits/stats | credits.view | open count, unspent value, applied to date, count from overpayments |
| GET | /customer-credits/meta | credits.view | source + status enums |
| POST | /customer-credits | credits.create | staff-issued credit note: `{customer_id, amount, currency?, reason}`. System credits (overpayment / invoice_adjustment) never come through here |
| GET | /customer-credits/{id} | credits.view | includes the `applications` ledger |
| POST | /customer-credits/{id}/void | credits.delete | unapplied only; **422** once any part has been applied |

## Inventory (Module 9)
| Method | Endpoint | Permission | Notes |
|--------|----------|-----------|-------|
| GET | /warehouses | warehouses.view | with stock-item counts |
| POST/PUT/DELETE | /warehouses(/{id}) | warehouses.create/.update/.delete | delete **422** if it still holds stock |
| GET | /inventory/stock | inventory.view | filters: `q`, `warehouse_id`, `filter` (low/out) |
| GET | /inventory/stats, /inventory/meta | inventory.view | warehouses/tracked SKUs/low/out/stock value |
| GET | /inventory/movements | inventory.view | ledger; filters `product_id`, `warehouse_id`, `type` |
| POST | /inventory/adjust | inventory.adjust | `{product_id, warehouse_id, value, mode:set\|delta, note?}` |
| POST | /inventory/move | inventory.adjust | `{product_id, warehouse_id, direction:receive\|issue, quantity, unit_cost?}` |
| GET/POST | /inventory/transfers | inventory.view / .transfer | `items[]` `{product_id, quantity}`; source≠destination |
| GET | /inventory/transfers/{id} | inventory.view | with items |
| POST | /inventory/transfers/{id}/action | inventory.transfer | `{action: ship\|receive\|cancel}` — moves stock via the ledger |
| GET | /inventory/barcodes/{productId} | inventory.view | |
| POST/DELETE | /inventory/barcodes(/{id}) | inventory.update | one primary barcode per product |

## Purchase (Module 8)
| Method | Endpoint | Permission | Notes |
|--------|----------|-----------|-------|
| GET/POST | /vendors | vendors.view / .create | filters `q`, `status` |
| GET/PUT/DELETE | /vendors/{id} | vendors.* | soft delete |
| GET/POST | /purchase-requests | purchase_requests.view / .create | `items[]` `{product_id?, name, quantity, estimated_price}` |
| GET/PUT/DELETE | /purchase-requests/{id} | purchase_requests.* | edit only while draft/rejected |
| POST | /purchase-requests/{id}/submit | purchase_requests.update | opens approval or auto-approves |
| POST | /purchase-requests/{id}/convert | purchase_orders.create | `{vendor_id}` → new draft PO |
| GET/POST | /purchase-orders | purchase_orders.view / .create | `stats` = open/awaiting/spend etc.; `items[]` with tax |
| GET/PUT/DELETE | /purchase-orders/{id} | purchase_orders.* | edit only while draft |
| POST | /purchase-orders/{id}/confirm | purchase_orders.update | opens approval or auto-confirms |
| POST | /purchase-orders/{id}/receive | inventory.adjust | `{lines?:[{item_id,quantity}]}`; posts stock, tracks received_quantity |
| POST | /purchase-orders/{id}/close, /cancel | purchase_orders.update | |
| GET | /approvals/mine | (any authenticated) | approvals awaiting the caller |
| POST | /approvals/{id}/act | (approver only, enforced) | `{action: approve\|reject, comment?}` |
| GET/POST/PUT/DELETE | /approvals/workflows(/{id}) | purchase_orders.approve | ordered `approver_ids`, `min_amount`, `document_type` |

## Helpdesk (Module 10)
| Method | Endpoint | Permission | Notes |
|--------|----------|-----------|-------|
| GET | /tickets | tickets.view | filters: `q`, `status` (open=new+open+pending), `priority`, `category_id`, `assigned_to` (me/unassigned/id), `breaching=1`; sorted by priority |
| GET | /tickets/stats, /tickets/meta | tickets.view | meta = categories, sla_policies, agents, enums, next no |
| POST | /tickets | tickets.create | SLA deadlines auto-applied by priority |
| GET/PUT/DELETE | /tickets/{id} | tickets.view/.update/.delete | changing priority re-derives SLA |
| POST | /tickets/{id}/replies | tickets.update | `{body, internal?, author_type?}`; first public agent reply stamps first-response |
| POST | /tickets/{id}/assign | tickets.assign | `{user_id?}`; assigning opens a new ticket |
| POST | /tickets/{id}/status | tickets.update | new/open/pending/resolved/closed; stamps resolved/closed, reopen bumps count |
| POST | /tickets/{id}/escalate | tickets.update | `{escalated_to?, reason?, note?}`; records a level, reassigns, bumps priority |

## Knowledge Base (Zoho gap #8, module `kb`)
Zoho "Solutions". Bodies are **plain text** (rendered pre-wrap on the portal — no HTML). Article
`status` (draft|published) and `visibility` (internal|public) are independent; the portal shows
only those that are **both** published **and** public. `feature:kb` (Professional+).

| Method | Endpoint | Permission | Notes |
|--------|----------|-----------|-------|
| GET/POST | /kb/articles | kb.view / .create | filters: `q`, `category_id`, `status`, `visibility` |
| GET | /kb/articles/meta | kb.view | categories, statuses, visibilities |
| GET/PUT/DELETE | /kb/articles/{id} | kb.view/.update/.delete | slug auto-derived, unique per company; publishing stamps `published_at` |
| POST | /kb/categories | kb.create | `{name, code}` |

## Documents (Zoho gap #9)
Staff document library, separate from per-record `attachments`. Files live on the **private
`local` disk**; there is **no public URL** — the only way to retrieve a file is the authenticated
streaming download, which resolves the row through the company-scoped model first (cross-tenant id
→ 404). `feature:documents` (Professional+). 15 MB limit; `mimetypes` whitelist.

| Method | Endpoint | Permission | Notes |
|--------|----------|-----------|-------|
| GET | /documents | documents.view | filters: `q`, `folder_id` |
| GET | /documents/meta | documents.view | folders + counts |
| POST | /documents | documents.create | multipart: `file` + `name?` + `description?` + `folder_id?`; filename is server-generated (hashed) |
| GET | /documents/{id} | documents.view | metadata |
| GET | /documents/{id}/download | documents.view | streams from the private disk |
| PUT | /documents/{id} | documents.update | metadata only (name/folder/description) |
| POST | /documents/{id}/file | documents.update | replace the file (old file removed after the row is repointed) |
| DELETE | /documents/{id} | documents.delete | removes row **and** file (no soft delete) |
| POST/PUT/DELETE | /documents/folders[/{id}] | documents.create/.update/.delete | folder delete → its documents become unfiled |

## Forecasts (Zoho gap #10)
Quota vs. achieved per user per period (month|quarter). Achieved = closed-won deals (`won_at` in
period); pipeline = open deals with `expected_close_date` in period (gross + weighted). `feature:forecasts`.

| Method | Endpoint | Permission | Notes |
|--------|----------|-----------|-------|
| GET | /forecasts?period_type&period_start | forecasts.view | board: per-user target/closed/pipeline/forecast/attainment/gap + totals |
| GET | /forecasts/meta | forecasts.view | active users + current period starts |
| GET | /forecasts/targets?period_type&period_start | forecasts.view | every active user with their current target (editor grid) |
| PUT | /forecasts/targets | forecasts.manage | `{period_type, period_start, targets:[{user_id, target_amount}]}` — upsert; a 0/absent amount clears that user's target |

## Manufacturing / BOM (Enterprise-only)
Bill of materials (component lines per finished-good product) + immediate atomic builds. Every
stock change goes through the one row-locked ledger writer; a build is one transaction (all
components consumed + finished good produced, or nothing). `feature:manufacturing`.

| Method | Endpoint | Permission | Notes |
|--------|----------|-----------|-------|
| GET | /manufacturing/boms | manufacturing.view | products that have a BOM (manufacturable) |
| GET | /manufacturing/meta | manufacturing.view | products (component picker) + warehouses |
| GET | /manufacturing/products/{id}/bom | manufacturing.view | a finished good's component lines |
| PUT | /manufacturing/products/{id}/bom | manufacturing.manage | `{lines:[{component_product_id, quantity}]}` — replaces the set; **422** on self-reference or a cycle |
| GET | /manufacturing/products/{id}/availability?warehouse_id&quantity | manufacturing.view | `{buildable, shortfalls[]}` |
| GET/POST | /manufacturing/builds | manufacturing.view / .build | POST `{product_id, warehouse_id, quantity, notes?}` — **422** naming short components (stock rolled back) if insufficient |
| GET | /manufacturing/builds/{id} | manufacturing.view | build + consumed components |

## Email (Module 6)
Outbound sending is real (SMTP via `SmtpMailer`/`Illuminate\Mail\MailManager::build()`), per
`email_accounts` row rather than one app-wide mailer — each account holds its own encrypted SMTP
credentials (`config`, same pattern as `chat_channels.config`). A failed send never errors the
HTTP request: the `emails` row still persists with `status=failed` and a human-readable `error`;
`send:true`/`POST .../send` return 200/201 either way, so check `data.status`, not just the HTTP
code. Local dev has no real provider configured by default — point an account's config at
`host: mailpit, port: 1025` (no auth/TLS) to send-and-inspect at http://localhost:8025.

| Method | Endpoint | Permission | Notes |
|--------|----------|-----------|-------|
| GET | /emails | email.view | filters: `q`, `folder` (inbox/sent/drafts), `account_id`, `related_type`+`related_id` |
| GET | /emails/stats, /emails/meta | email.view | meta = accounts, templates (subject+body), related types |
| POST | /emails | email.send | compose; `send:true` attempts real delivery immediately (else draft). `to[]` required; a successful send links a timeline entry |
| GET/PUT/DELETE | /emails/{id} | email.view / .send | |
| POST | /emails/{id}/send | email.send | send a saved draft, or retry a failed one |
| POST | /emails/inbound | email.send | **SalesInbox** — ingest a received message (provider inbound-parse webhook or manual push). Idempotent by `message_id`; matches sender to Contact→Customer/Customer/Lead; `in_reply_to` threads onto the answered message. Body: `{from_address, from_name?, to?, cc?, subject?, body_html?, message_id?, in_reply_to?, received_at?, account_id?}` |
| GET | /email-accounts | email.view | includes `configured` (SMTP host/port set) |
| GET | /email-accounts/{id} | email.view | edit-form shape — secrets shown only as set/unset, never their value |
| POST/PUT | /email-accounts(/{id}) | email.manage_templates | `config: {host, port, encryption, username, password}` — a blank secret on update keeps the stored value |
| POST | /email-accounts/{id}/test | email.manage_templates | sends a real test email (there's no cheaper live SMTP handshake); `{to?}` defaults to the account's own address |
| POST | /email-accounts/{id}/fetch | email.manage_templates | **SalesInbox** — pull new mail over IMAP now (`config` gains `imap_host/imap_port/imap_encryption/imap_username/imap_password/imap_folder`). Returns `{ok, ingested}`; a clean `ok:false` message when IMAP isn't configured or the host is unreachable — never a 500 |
| POST/PUT/DELETE | /email-templates(/{id}) | email.manage_templates | subject + body_html |

## Marketing (Module 11)
| Method | Endpoint | Permission | Notes |
|--------|----------|-----------|-------|
| GET | /campaigns | campaigns.view | filters: `q`, `type`, `status` |
| GET | /campaigns/stats, /campaigns/meta | campaigns.view | meta = templates, email accounts, SMS providers |
| POST | /campaigns/preview-audience | campaigns.view | `{type, audience:{source, filters}}` → total + reachable count |
| POST | /campaigns | campaigns.create | `audience` required; sets draft/scheduled |
| GET/PUT/DELETE | /campaigns/{id} | campaigns.view/.update/.delete | edit only while draft/scheduled |
| GET | /campaigns/{id}/recipients | campaigns.view | materialised recipients |
| POST | /campaigns/{id}/launch | campaigns.launch | materialises recipients + messages, marks sent |
| POST | /sms-providers | campaigns.create | |

## Settings (admin surface — no new tables)
| Method | Endpoint | Permission | Notes |
|--------|----------|-----------|-------|
| GET/PUT | /settings/company | settings.view / .update | company profile |
| GET/POST | /settings/branches | settings.view / .update | with user counts |
| PUT/DELETE | /settings/branches/{id} | settings.update | delete **422** if users assigned |
| GET/POST/PUT/DELETE | /settings/departments(/{id}) | settings.view / .update | same guard |
| GET | /users | users.view | filters `q`, `role`, `is_active`; `meta` = roles/branches/departments |
| POST/PUT/DELETE | /users(/{id}) | users.create/.update/.delete | password on create (admin-set); **can't delete self**; only the tenant **Owner** (or a platform admin) may grant **Owner** |
| GET | /roles, /roles/permissions | roles.view | roles with counts; permissions grouped by module — **scoped to the caller's tenant** (spatie teams) |
| GET | /roles/{id} | roles.view | role with its permission names |
| POST/PUT/DELETE | /roles(/{id}) | roles.create/.update/.delete | **Owner role is protected**; delete **422** if assigned |
| POST | /roles/{id}/clone | roles.create | new role in the same tenant, `name` + a copy of the source role's permissions; source may be protected (Owner) |

## API Keys (third-party auth, gated `feature:api_keys` — Professional+ plans)
| Method | Endpoint | Permission | Notes |
|--------|----------|-----------|-------|
| GET | /api-keys | api_keys.view | `key_prefix` only — the full key is never returned again after creation |
| POST | /api-keys | api_keys.create | `{name, user_id?, expires_at?}` — `user_id` defaults to the caller; response's `plain_key` is shown **once** |
| PUT | /api-keys/{id} | api_keys.update | `{name?, user_id?, expires_at?, is_active?}` — set `is_active:false` to revoke |
| DELETE | /api-keys/{id} | api_keys.delete | |

A key resolves to a real `user_id` and acts with that user's full roles/permissions — there is no
separate scope/ability system. Authenticate with `X-Api-Key: <key>` instead of `Authorization:
Bearer`; every other route, permission check, and company-scoping rule behaves identically. A
revoked, expired, or unknown key returns **401**.

## Customer portal (self-service — second auth boundary, not staff)
A `Contact` (not a `User`) logs in on its own `portal` JWT guard/provider, separate from the staff
`api` guard. Enable access via `PUT /customers/{id}/contacts/{contactId}/portal` (see Customers
above) — the contact needs an email, and its email must be unique among *all* portal-enabled
contacts in the whole instance (login is by email alone, no tenant selector).

| Method | Endpoint | Auth | Notes |
|--------|----------|------|-------|
| POST | /portal/login | none (throttled 5/min) | `{email, password}` → `{access_token, contact}` |
| GET  | /portal/me | portal guard | |
| POST | /portal/logout | portal guard | |
| GET  | /portal/invoices | portal guard | scoped to the contact's own customer only |
| GET  | /portal/invoices/{id} | portal guard | 404 (not 403) if the invoice belongs to another customer — avoids confirming the ID exists |
| GET  | /portal/tickets | portal guard | |
| GET  | /portal/tickets/{id} | portal guard | internal notes (`is_internal=true`) are never included |
| POST | /portal/tickets | portal guard | `{subject, description?, priority?}` — creates with `channel=web`, no `assigned_to` |
| POST | /portal/tickets/{id}/replies | portal guard | `{body}` — posted as `author_type=customer` |
| GET | /portal/kb/articles | portal guard | published+public only, company-wide (**no** customer_id filter); filters `q`, `category_id` |
| GET | /portal/kb/articles/{id} | portal guard | 404 for internal/draft or another company's article; atomically increments `view_count` |
| GET  | /portal/quotations | portal guard | scoped to the contact's own customer; `status=draft` quotations are never shown — a customer only sees what staff has sent |
| GET  | /portal/quotations/{id} | portal guard | 404 if it belongs to another customer or is still a draft |
| POST | /portal/quotations/{id}/sign | portal guard | `{signed_name, signature_data}` (a base64 PNG data URI, capped at 200KB) — only a `sent` quotation can be signed; **422** on a draft/already-signed/rejected/expired/converted one. Sets `status=accepted`, stamps `signed_at`/`signed_name`/`signed_ip`/`signature_data`, fires the `quotation.signed` workflow event |

Login is rejected if `portal_enabled` is false or the linked customer's status is `blocked`/
`archived` — checked both at login and on every subsequent request (a customer blocked mid-session
is locked out immediately, not just on next login). All portal queries filter explicitly by
`company_id` **and** `customer_id` from the authenticated contact; they never rely on
`BelongsToCompany`'s global scope, since that scope reads the default (staff) guard and would
silently apply no filter at all under a portal-only request. Portal requests are **not**
audit-logged (they sit outside the staff `jwt.auth`/`scope.company`/`audit` middleware group).
There is no email-invite flow yet — a staff member sets the initial password directly.

**E-signature is a captured assent, not a certificate-based signature**: a typed name plus a
drawn (canvas) signature image, stamped with a timestamp and the requester's IP — the same kind
of lightweight "click/draw to accept" flow most CRMs use, not a DocuSign-style cryptographic
signature. Deliberately requires portal login rather than an anonymous emailed link (which would
be a *third* auth boundary in a codebase that already has two) — if the customer doesn't have
portal access yet, grant it via the same `PUT /customers/{id}/contacts/{contactId}/portal` used
for invoices/tickets.

## Reports (Module 13)
| Method | Endpoint | Permission | Notes |
|--------|----------|-----------|-------|
| GET | /reports/datasets | reports.view | registry: datasets → dimensions/measures/filters (labels only) |
| POST | /reports/run | reports.view | ad-hoc `{dataset, dimension, measures[], filters}` → columns + grouped rows |
| GET/POST | /reports | reports.view / .create | saved reports |
| GET | /reports/{id} | reports.view | returns the report spec + its run result |
| PUT/DELETE | /reports/{id} | reports.create | |
| POST | /reports/{id}/export | reports.export | generates a CSV; returns its URL |
| GET | /reports/{id}/exports | reports.view | past exports |
| GET/POST | /reports/dashboards | reports.view / .create | custom dashboards (report-widget layout: `[{report_id, size}]`, `size` one of `half`\|`full`) |
| PUT/DELETE | /reports/dashboards/{id} | reports.create | `layout.*.report_id` must be an existing, non-deleted saved report in the caller's own company — validated server-side, not just client-side |

Chart types are `table`\|`bar`\|`line`\|`pie`, rendered client-side via Chart.js (`ReportChart.vue`,
reused by both the ad-hoc builder and dashboard widgets — bar/line support multiple measures as
separate series, pie always uses the first measure only). A dashboard widget whose `report_id` no
longer resolves (soft-deleted report) renders an inline "unavailable" placeholder rather than
breaking the rest of the grid — each widget's data is fetched independently and in parallel.

## Workflow (Module 14)
| Method | Endpoint | Permission | Notes |
|--------|----------|-----------|-------|
| GET | /workflows | workflows.view | filters `q`, `entity`; `stats`/`meta` (entities, triggers, `events` map per entity, action types, operators) |
| GET/POST | /workflows | workflows.view / .create | `actions[]` `{type, config}`, `conditions[]` `{field, op, value}`, plus `trigger_event` (required if `trigger_type=event`, must be one of `meta.events[entity]`) or `schedule_cron` (required if `trigger_type=schedule`, standard 5-field cron) |
| GET/PUT/DELETE | /workflows/{id} | workflows.view/.update/.delete | detail includes actions + last 15 runs |
| POST | /workflows/{id}/run | workflows.update | `{subject_id?}` — evaluates conditions, runs actions, returns the run log |

Triggers are real, not structural. `trigger_type=event` workflows fire synchronously the moment the
matching transition happens in the owning service — `lead.created`/`lead.status_changed`/
`lead.converted`, `deal.created`/`deal.stage_changed`/`deal.won`/`deal.lost`,
`ticket.created`/`ticket.status_changed`/`ticket.escalated`, `customer.created`,
`invoice.created`/`invoice.paid` — see `Workflow::EVENTS` for the authoritative list. A workflow
failure never surfaces as the triggering request's failure; it's logged and the request completes
normally. `trigger_type=schedule` workflows are checked every minute by `workflows:run-scheduled`
(registered on the scheduler container's `Schedule::command(...)->everyMinute()`), which runs any
workflow whose cron is due since `last_run_at`.

The `webhook` action is also real: `WebhookDispatcher` sends an HMAC-signed HTTP POST (5s timeout,
no redirects followed) with a JSON body `{event, entity, subject_id, workflow_id, workflow_name,
timestamp}` and header `X-Krama-Signature: sha256=<hmac>`. The signing key is derived from
`APP_KEY` + the workflow id (`hash_hmac('sha256', 'workflow:'.$id, config('app.key'))`) — nothing
extra is stored, since `WorkflowResource` returns action config (including the target `url`)
verbatim to anyone with `workflows.view`. Outside `APP_ENV=local`, the target URL is rejected if
it isn't `http`/`https`, or resolves to a literal loopback/private/link-local IP, or is
`localhost`/`*.localhost` — this is a basic SSRF guard, not exhaustive DNS-rebinding protection.

## AI Assistant (Module 15)
All under `/ai`, gated by `ai.use`.

| Method | Endpoint | Notes |
|--------|----------|-------|
| GET/POST | /ai/conversations | list the caller's chats / start a new one |
| GET/DELETE | /ai/conversations/{id} | thread with messages / delete |
| POST | /ai/conversations/{id}/messages | `{content}` — stores the message and a heuristic assistant reply grounded in real data |
| GET | /ai/insights | current (non-dismissed) insights |
| POST | /ai/insights/generate | regenerate from live data |
| POST | /ai/insights/{id}/dismiss | |
| GET | /ai/predictions | pipeline forecast, top lead, churn risk |
| POST | /ai/predictions/generate | recompute |

## HR (Module 12)
| Method | Endpoint | Permission | Notes |
|--------|----------|-----------|-------|
| GET | /employees | employees.view | filters `q`, `status`, `department_id`; `stats`/`meta` (departments, managers, types, next no) |
| GET/POST | /employees | employees.view / .create | new employees get current-year leave balances |
| GET/PUT/DELETE | /employees/{id} | employees.view/.update/.delete | detail includes leave balances + recent requests |
| GET/POST | /attendance | attendance.view / .create | `?date=`; POST upserts one row/day, computes hours from check_in/out |
| GET/POST | /leave/types | leave.view / .create | |
| GET/POST | /leave/requests | leave.view / .create | create computes days, **422** on insufficient balance |
| POST | /leave/requests/{id}/decide | leave.approve | `{action: approve\|reject\|cancel}` — approve deducts balance + stamps attendance |

## Social / Chat Inbox (Module 16)
| Method | Endpoint | Permission | Notes |
|--------|----------|-----------|-------|
| GET  | /chat/conversations | chat.view | filters: `status`, `channel_type`, `channel_id`, `assigned_to` (`me`/`unassigned`/id), `unread`, `q`, `per_page` |
| GET  | /chat/conversations/counts | chat.view | rail counters: all/open/pending/resolved/mine/unassigned/unread |
| GET  | /chat/conversations/{id} | chat.view | full thread; marks it read |
| POST | /chat/conversations/{id}/reply | chat.reply | `{body, direction?, attachments[]}` — `direction` is `outbound` (default) or `note`. Send `multipart/form-data` to attach media; `body` is optional when at least one attachment is present |
| POST | /chat/conversations/{id}/assign | chat.assign | `{user_id}` — null to unassign |
| POST | /chat/conversations/{id}/status | chat.close | `{status}` open\|pending\|snoozed\|resolved\|closed |
| GET | /chat/conversations/{id}/crm-context | chat.view | linked CRM identity, email/phone match suggestions, and link history; optional `q` searches Leads, Contacts, and Accounts |
| POST | /chat/conversations/{id}/crm-link | chat.link_crm | `{type: lead\|contact\|account, id}`; target must belong to the same tenant |
| DELETE | /chat/conversations/{id}/crm-link | chat.link_crm | unlink the social identity while preserving history |
| POST | /chat/conversations/{id}/create-lead | chat.link_crm + leads.create | create through the normal Lead workflow and link the identity; refuses a matching open Lead |
| GET  | /chat/channels | chat.view | active channels **grouped by provider**, each with its accounts and open-conversation counts (credentials never exposed) |
| GET  | /chat/canned-responses | chat.view | quick replies |

**422 on reply** means either the channel's 24-hour service window has closed (only an approved
template may be sent; internal notes are exempt), or an attachment failed validation — the
allowed mimes are jpeg/png/gif/webp, mp4/quicktime/webm, mpeg/ogg/wav/mp4 audio, and pdf,
each up to 15 MB, 10 files per message.

Outbound messages persist with `status=queued`; no provider transport is wired up yet.

### Inbox channel settings (Module 18 — connect API credentials per channel)
All routes require `chat.manage_channels`. Credentials live in the encrypted `chat_channels.config`
column; **secret fields are write-only** — reads return only whether each is set, never its value.

| Method | Endpoint | Notes |
|--------|----------|-------|
| GET    | /chat/settings/channels | list every channel with `credentials` map (`{set, value, secret}` per field), `configured`, `is_active`, `conversations_count`, `webhook` |
| GET    | /chat/settings/channels/meta | per-type field schema for the UI (labels + `required`/`secret` flags, no values) |
| POST   | /chat/settings/channels | `{type, name, config{}, is_active?}` — `type` ∈ whatsapp, messenger, instagram, telegram, tiktok, sms, webchat; name unique per company+type |
| GET    | /chat/settings/channels/{id} | one channel (same shape as list) |
| PUT    | /chat/settings/channels/{id} | `{name?, config{}, is_active?}` — a **blank secret keeps** the stored value; a blank non-secret clears it |
| POST   | /chat/settings/channels/{id}/test | structural check: 200 when every required field is set, else 422 with `errors.missing[]`. No live provider handshake is wired up |
| DELETE | /chat/settings/channels/{id} | soft-delete the channel |

The `account_field` of each type (e.g. `phone_number_id`, `bot_username`, `page_id`) is mirrored
into `external_account_id`. `webhook` is the informational callback path to register with the
provider — no live receiver is wired in this build.

## Dynamics 365 Business Central (Module 17 — pull sync)
| Method | Endpoint | Permission |
|--------|----------|-----------|
| GET    | /integrations/dynamics | integrations.view |
| POST   | /integrations/dynamics | integrations.manage |
| PUT    | /integrations/dynamics/{id} | integrations.manage |
| DELETE | /integrations/dynamics/{id} | integrations.manage |
| POST   | /integrations/dynamics/{id}/test | integrations.manage |
| GET    | /integrations/dynamics/{id}/runs | integrations.view |
| GET    | /integrations/dynamics/{id}/runs/{runId} | integrations.view |
| POST   | /integrations/dynamics/{id}/mappings | integrations.manage |
| PUT    | /integrations/dynamics/{id}/mappings/{mappingId} | integrations.manage |
| DELETE | /integrations/dynamics/{id}/mappings/{mappingId} | integrations.manage |
| POST   | /integrations/dynamics/{id}/mappings/{mappingId}/sync | integrations.sync |

`GET /integrations/dynamics` also returns `supported_bc_entities` (entity sets a mapping may
target) and `implemented_crm_entities` (those that actually have a syncer today:
`company`, `customer`, `product`, `invoice`). Requesting a sync for anything else returns
**422** rather than queueing a job that is guaranteed to fail.

**All syncers are `pull` only** (BC → CRM). A mapping with `direction` `push` or
`bidirectional` fails the run with a message naming the unsupported direction — push writes
into a customer's live ERP and there is no tenant available to verify it against. See
[`DYNAMICS_BC_SYNC_SCOPE.md`](DYNAMICS_BC_SYNC_SCOPE.md).

| CRM entity | BC entity set | Behaviour |
|---|---|---|
| `company` | `companies` | crosswalk only (reference implementation) |
| `customer` | `customers` | upserts CRM customers, matched on `number` → `customer_no` |
| `product` | `items` | upserts CRM products, matched on `number` → `sku`; BC `Inventory` → `goods`, else `service` |
| `invoice` | `salesInvoices` | **reconcile only — never creates or edits an invoice.** Links BC invoices to existing CRM ones by number; unmatched ones and total mismatches are logged as run issues |

Sync behaviour worth knowing: unchanged rows are `skipped` via a `payload_hash`, so re-running
is cheap and non-destructive; a row with no `number` is skipped with a warning rather than
written; a BC row whose CRM counterpart is **soft-deleted** is refused with a warning instead
of being resurrected; and `sync_cursor` (a high-water mark on `lastModifiedDateTime`) advances
only when the run had **zero failures**, so a partial run never skips the rows that failed.

`POST .../test` returns **200 with `ok:false`** on failure — a failed probe is a result to
render, not a server error. The stage (`auth`/`request`) and the provider's own message are
included, and the outcome is written to `bc_connections.status` / `last_error`.

`client_secret` is never returned by any endpoint. It is `encrypted` + `hidden` on the model
and excluded from the Resource whitelist.

## Tenant branding (public, unauthenticated)
| Method | Endpoint       | Notes |
|--------|-----------------|-------|
| GET    | /tenant-info    | Resolves the caller's `Host` header to a tenant via `companies.subdomain` and returns `{name, logo_url, primary_color}`. **404, never an error, when the host doesn't resolve** — plain `localhost` included. Read-only/advisory only; see "Per-tenant subdomains" in ARCHITECTURE.md for why this must never gate auth. |

## Platform console (`platform.admin` only — no permission:/feature: gate)
| Method | Endpoint                                      | Notes |
|--------|------------------------------------------------|-------|
| GET    | /platform/companies                            | list tenants: plan, users_count, is_active, is_platform |
| GET    | /platform/companies/{id}                       | one tenant + its last 20 access grants |
| PUT    | /platform/companies/{id}/plan                  | `{plan_code}` — takes effect immediately, no cache to flush |
| POST   | /platform/companies/{id}/access-grants         | `{reason, hours (1-8)}` — records a bounded, revocable authorization; **does not itself change what any request can read or write** — see "Editions"/"Platform console" in ARCHITECTURE.md |
| GET    | /platform/access-grants                        | all grants, newest first |
| POST   | /platform/access-grants/{id}/revoke            | only affects an active (unexpired, unrevoked) grant — 404 otherwise |

## System
| GET  | /notifications              | latest 30 notifications + unread count; reminder payloads include a safe in-app destination |
| POST | /notifications/{id}/read    | mark one read |
| POST | /notifications/read-all     | mark all read |
| GET  | /search?q=term              | global search across modules |
| GET  | /audit-logs                 | paginated audit trail (perm: audit.view) |

## Rate limiting
- Auth endpoints: 5 requests / minute / IP
- All others: 60 requests / minute (Laravel throttleApi default)

## Error codes
| Code | Meaning |
|------|---------|
| 401  | unauthenticated / token expired (see `token_error`) |
| 403  | no permission / disabled account / 2FA required |
| 404  | not found — a missing route or a `findOrFail()` miss, always the app's JSON envelope |
| 422  | validation failed (see `errors`) |
| 429  | rate limited |
| 500  | server error |

`ModelNotFoundException` and unmatched routes are rendered explicitly in `bootstrap/app.php`
(`withExceptions`) before the generic `Throwable` catch-all — without that, a `findOrFail()` miss
would fall through to the catch-all, which in production (`APP_DEBUG=false`) turns *any* uncaught
exception into a flat 500, wrongly downgrading a clean "not found" into a "server error". Found
while testing the dashboards feature (2026-08-29); fixed for every `findOrFail()` in the app, not
just Reports.

## Modules 2–15
Endpoints follow REST conventions and are added as each module ships,
e.g. `GET/POST/PUT/DELETE /leads`, `/customers`, `/deals`, etc.
Swagger UI (l5-swagger) is available at `/api/documentation` once annotations are generated.
## Project Management

All endpoints require staff JWT authentication, tenant scope, the `projects` plan feature, and
the permission shown by the route middleware.

- `GET /api/v1/projects`, `/stats`, `/meta` — list, KPIs, and form metadata
- `POST /api/v1/projects`; `GET|PUT|DELETE /api/v1/projects/{id}` — Project CRUD
- `POST /api/v1/projects/from-deal/{dealId}` — create one Project from a won Deal
- `POST|DELETE /api/v1/projects/{id}/members[/{userId}]` — team allocation
- `POST|PUT|DELETE /api/v1/projects/{id}/milestones[/{milestoneId}]`
- `POST|PUT|DELETE /api/v1/projects/{id}/tasks[/{taskId}]` — delivery tasks and progress
- `GET /api/v1/projects/{id}/tasks/{taskId}` — task detail with subtasks, dependencies,
  comments, files, and activity history
- `POST /api/v1/projects/{id}/tasks/{taskId}/comments`
- `POST|DELETE /api/v1/projects/{id}/tasks/{taskId}/dependencies[/{dependsOnId}]`
- `POST|DELETE /api/v1/projects/{id}/tasks/{taskId}/attachments[/{attachmentId}]`
- `GET|POST /api/v1/projects/{id}/time-entries` — list/log time and financial summary
- `PUT|DELETE /api/v1/projects/{id}/time-entries/{entryId}` — edit/delete unapproved time
- `POST /api/v1/projects/{id}/time-entries/{entryId}/decision` — approve/reject submitted time
- `GET /api/v1/projects/workload?from=YYYY-MM-DD&to=YYYY-MM-DD` — capacity and utilization
- `GET /api/v1/projects/templates` — list tenant project templates
- `POST /api/v1/projects/templates/from-project/{projectId}` — save a Project blueprint
- `PUT|DELETE /api/v1/projects/templates/{id}` — enable, rename, or remove a template
- `POST /api/v1/projects/templates/{id}/create-project` — instantiate a template from a new start date
- `GET|POST /api/v1/projects/automation-rules` — list/create Project automation rules
- `PUT|DELETE /api/v1/projects/automation-rules/{id}` — enable, update, or remove a rule
- `GET /api/v1/projects/management-report?from=YYYY-MM-DD&to=YYYY-MM-DD` — delivery health,
  task completion, approved hours, and currency-aware project financial performance

Project task create/update payloads may include `recurrence_frequency` (`daily`, `weekly`, or
`monthly`), `recurrence_interval`, and `recurrence_end_date`. Completing a recurring task creates
the next occurrence once. The hourly `projects:run-automations` command provides recovery for
recurrence generation and executes idempotent overdue rules.
