# Krama CRM — Database Schema (ERD)

Full schema for all 15 modules. Module 1 tables are implemented now;
remaining migrations land as each module is built. Conventions:
- Every table: `id` PK, `created_at`, `updated_at` (soft deletes where useful)
- Every domain table: `company_id` FK for multi-tenancy
- Money: DECIMAL(15,2); currency: CHAR(3)

## Foundation (implemented)
- **companies** — root tenant: name, code(unique), subdomain(unique, nullable — TenantResolver
  branding lookup only, see ARCHITECTURE.md), base_currency, logo, primary_color, language,
  is_platform (marks the Krama master org), plan_id...
- **plans** — code(unique), name, sort_order, is_active — the edition catalogue (Starter /
  Professional / Enterprise); module lists live in `TenantProvisioner::planDefinitions()`, not the DB
- **plan_features** — plan_id, module; unique(plan_id, module) — which modules an edition includes,
  enforced by the `feature:` route middleware (independent of RBAC — a role can hold `x.view` and
  still be blocked if the tenant's plan doesn't include module `x`)
- **support_access_grants** — company_id, granted_by(user), reason, expires_at, revoked_at — an
  **authorization record**, not a live session: it documents that a platform admin was allowed,
  for a bounded window and a stated reason, to act on a tenant's behalf. Does not currently change
  what any request can read/write (see "Editions" in ARCHITECTURE.md for why)
- **branches** — company_id, code, manager_id; unique(company_id, code)
- **departments** — company_id, branch_id, code, head_id
- **users** — company_id, branch_id, department_id, email(unique per company), 2FA, language...
- **roles / permissions / model_has_*** — spatie RBAC, team scoped by company_id
- **api_keys** — company_id, user_id (the key acts as this user — inherits their roles/permissions
  unchanged), name, key_prefix (shown in UI), key_hash (sha256, unique; the plaintext key itself is
  never stored), last_used_at, expires_at, is_active, created_by. Third-party auth via `X-Api-Key`
  header, alternative to a JWT session — see `JwtAuthenticate::handleApiKey()` and API.md
- **currencies** — code, symbol, exchange_rate, is_base
- **audit_logs** — polymorphic auditable, old/new JSON, ip, user_agent
- **login_history** — user_id, ip, status(success/failed)
- **settings** — company_id, key, value(JSON); unique(company_id, key)
- **dashboard_widgets** — user_id, widget_type, config(JSON), position
- **notifications, jobs, job_batches, failed_jobs, cache** — Laravel system tables

## Module 2 — Leads (implemented)
- **lead_sources** — company_id, name, code, is_active; unique(company_id, code)
- **lead_statuses** — company_id, name, code, color, sort_order, is_default, is_won,
  is_lost; unique(company_id, code)
- **leads** — company_id, lead_no, name (the person), company_name, title, email, phone,
  mobile, website, source_id, status_id, score(0-100), rating(hot|warm|cold), owner_id,
  branch_id, estimated_value, currency, expected_close_date, last_contacted_at,
  converted_to_customer_id, converted_at, notes, soft deletes; unique(company_id, lead_no)
- **lead_assignment_rules** — company_id, name, priority, conditions(JSON, ANDed),
  strategy(specific|round_robin), assign_to_user_id, round_robin_user_ids,
  round_robin_cursor, is_active
- **attachments** *(shared, polymorphic)* — company_id, attachable_type/id, uploaded_by,
  disk, path, name, mime, size

**Two deliberate deviations from the original plan.** `lead_notes` and `lead_attachments`
were not created: notes go to the shared `timeline_activities` and files to the new shared
polymorphic `attachments`. One table per concern beats one per module, and deals, tickets
and quotes will reuse both rather than growing their own copies.

**The conversion loop is now closed.** `leads.converted_to_customer_id` is a real FK, and
this migration also adds the reverse FK on `customers.converted_from_lead_id`, which had to
be left unconstrained when Customers shipped first. Both are nullable, so the circular
reference is legal and cannot deadlock inserts.

**Scoring** is a transparent rubric in `LeadScoringService`, not a model: contactability
(email/phone), qualification (company/title/source), capped deal size, contact recency that
decays, and a stage adjustment (won +10, lost −20). `GET /leads/{id}/score` returns the same
breakdown the total is built from, so the UI can show its working. The hourly `leads:score`
command recomputes open leads and must bypass the company global scope — it runs
unauthenticated, and without `withoutGlobalScopes()` it silently scores nothing.

## Module 3 — Accounts and Contacts (implemented; `customers` is the Account table)

Deals and Contacts are connected through `deal_contact`, which stores each participant's buying
role and whether they are the Deal's primary Contact. Every pivot row also carries `company_id` for
tenant-safe reporting and integrity checks.
- **customer_groups** — company_id, name, code, discount_percent, payment_terms_days,
  is_active; unique(company_id, code)
- **customers** — company_id, customer_no, type(company|individual), group_id, owner_id,
  branch_id, name, legal_name, email, phone, mobile, website, tax_id, currency,
  credit_limit, payment_terms_days, status(active|on_hold|blocked|archived), notes,
  converted_from_lead_id, soft deletes; unique(company_id, customer_no).
  `converted_from_lead_id` is **unconstrained** — Leads (M2) ships next.
  `customer_no` is generated per company as `CUST-00001` by `CustomerService::nextCustomerNo()`.
- **contacts** — company_id, customer_id, name, title, email, phone, mobile, is_primary,
  notes, soft deletes. Exactly one primary per customer, enforced in the controller.
  Also the login identity for the customer self-service portal: `password` (hashed, nullable),
  `portal_enabled` (bool, default false), `last_login_at` — see the `portal` guard in
  `config/auth.php` and docs/API.md → "Customer portal".
- **addresses** *(shared, polymorphic)* — company_id, addressable_type/id,
  type(billing|shipping|other), label, line1, line2, city, state, postal_code, country,
  is_default. Used by customers and contacts now; vendors and employees later.
- **timeline_activities** *(shared, polymorphic)* — company_id, subject_type/id, user_id,
  type(note|call|email|meeting|status_change|system), title, body, meta(JSON), occurred_at.
  Distinct from `audit_logs`: that is a tamper record of field changes, this is the
  human-facing narrative shown on a record's detail page. `TimelineActivity::record()`
  is the helper services use.

**Effective payment terms** fall back from the customer to its group. Note the eager load
must select `customer_groups.payment_terms_days`, or the accessor silently reads null.

## Module 4 — Pipeline (implemented)
- **pipelines** — company_id, name, code, is_default, is_active, sort_order; unique(company_id, code)
- **pipeline_stages** — company_id, pipeline_id, name, code, color, **order_index**, probability,
  is_won, is_lost, **required_fields** (json, nullable), **allowed_next_stage_ids** (json,
  nullable) — Blueprint-style guided process: deal fields required before entering the stage, and
  which stages it may move to next. Both null/empty by default (unrestricted); enforced in
  `DealService`, configured via `PUT /pipelines/{id}/stages/{id}` — see API.md; unique(pipeline_id, code)
- **lost_reasons** — company_id, name, code, sort_order, is_active; unique(company_id, code)
- **deals** — company_id, deal_no, title, pipeline_id, stage_id, customer_id, lead_id, owner_id,
  branch_id, amount, currency, probability, status(open|won|lost), expected_close_date, won_at,
  lost_at, lost_reason_id, source, notes, soft deletes; unique(company_id, deal_no)
- **deal_products** — company_id, deal_id, **product_id (unconstrained until M7)**, name,
  description, quantity, unit_price, discount_pct, line_total, sort_order

**Stage is the source of truth for won/lost.** `deals.status` and `won_at`/`lost_at` are
materialised by `DealService` whenever the stage changes, so the KPI (which derives open deals
from `pipeline_stages.is_won/is_lost`) and the UI never disagree. Reuses the shared
`timeline_activities` and `attachments` tables; there are no per-deal note/attachment tables.

**Line items are authoritative.** When a deal carries `deal_products`, `DealService` rolls their
`line_total` up into `deals.amount` (qty × unit_price × (1 − discount%)). `product_id` is left
unconstrained until **M7 (Sales)** creates `products`; that migration adds the FK, mirroring how
`customers.converted_from_lead_id` was closed. `name`/`unit_price` stay denormalised so a line
survives a later product deletion.

**Column naming trap:** the stage order column is `order_index` (not `sort_order`), because
`DashboardService::pipeline()` groups and orders by it. Seed **exactly one** default pipeline —
that widget groups stages by company with no pipeline filter, so a second pipeline would merge
the funnel.

## Module 5 — Activities (implemented)
- **tasks** — company_id, title, description, status(open|in_progress|done|cancelled),
  priority(low|medium|high|urgent), **assigned_to**, created_by, **due_at**, completed_at,
  nullable `related` morph, soft deletes
- **meetings** — company_id, title, description, location, meeting_link, status(scheduled|
  completed|cancelled), organizer_id, start_at, end_at, nullable `related` morph, soft deletes
- **meeting_participants** — company_id, meeting_id, user_id (nullable), name/email (external
  guests), response(invited|accepted|declined|tentative)
- **calls** — company_id, subject, direction(inbound|outbound), status(scheduled|completed|
  missed|cancelled), phone, duration_seconds, user_id, notes, scheduled_at, occurred_at,
  nullable `related` morph, soft deletes
- **reminders** — company_id, user_id, title, remind_at, channel(in_app|email), is_sent,
  sent_at, nullable `related` morph

**Column contract:** `tasks` matches `DashboardService::tasksSummary()` exactly — `assigned_to`,
`status` (must include `open`/`in_progress`), `due_at`, `title`, `priority`. The polymorphic
`related` link points at a Deal, Lead or Customer; the API accepts short aliases (`deal`/`lead`/
`customer`) and stores the class. Logging a meeting/call against a related record also writes a
`timeline_activities` entry on that record.

## Module 6 — Email (implemented)
- **email_accounts** — company_id, name, email_address (unique per company), from_name,
  provider(smtp|gmail|outlook|ses — only smtp is actually wired), config (encrypted, SMTP
  host/port/encryption/username/password — same pattern as `chat_channels.config`), user_id
  (owner of a personal account), is_shared, is_default, is_active, signature
- **email_templates** — company_id, name, code, category, subject, body_html, is_active
- **emails** — company_id, email_account_id, direction(inbound|outbound), status(draft|queued|
  sent|failed|received), error (nullable, populated on a failed real send), from_address/name,
  to/cc/bcc (JSON), subject, body_html, template_id, message_id, nullable `related` morph
  (customer/lead/deal), opens, clicks, opened_at, sent_at, received_at, user_id, soft deletes
- **email_attachments** — company_id, email_id, disk, path, name, mime, size

**Real outbound delivery** via `SmtpMailer`, per `email_accounts` row rather than one app-wide
mailer. Sending an email linked to a record writes an `email` timeline entry on it (same
`related` short-alias map Activities uses) — only on an actual successful send. Open/click
counts are stored fields (seeded for the demo, not live-tracked); the folder scope maps
inbox=inbound, sent=outbound sent/queued, drafts=status draft. `email_attachments` remains
unpopulated — no upload endpoint exists yet, unrelated to this pass.

## Module 7 — Sales (implemented)
- **product_categories** — company_id, name, code, parent_id (self ref), is_active
- **tax_rates** — company_id, name, code, rate(%), is_inclusive, is_default, is_active
- **products** — company_id, sku, name, description, category_id, type(goods|service), unit,
  cost_price, sale_price, tax_rate_id, barcode, track_inventory (read by M9), reorder_level,
  is_active, soft deletes
- **quotations** — + quote_no, deal_id, status(draft|sent|accepted|rejected|expired|converted),
  issue_date, valid_until, converted_order_id, signed_at, signed_name, signed_ip,
  signature_data (base64 PNG data URI — the customer-portal e-signature capture)
- **sales_orders** — + order_no, quotation_id, deal_id, status(draft|confirmed|processing|
  fulfilled|cancelled), order_date, expected_date, converted_invoice_id
- **invoices** — + invoice_no, sales_order_id, **created_by**, **status**(draft|issued|
  partially_paid|paid|void), **issue_date**, due_date, amount_paid, balance
- shared header columns on all three: customer_id, owner_id, branch_id, currency, **price_book_id**
  (nullable, provenance only — see Price Books), subtotal, discount_total, tax_total,
  **grand_total**, notes, terms, soft deletes
- **{quotation,sales_order,invoice}_items** — company_id, parent_fk, product_id (soft link,
  denormalised name/unit_price), quantity, unit_price, discount_pct, tax_rate_id, tax_amount,
  line_total, sort_order
- **payments** — company_id, payment_no, invoice_id, customer_id, method(cash|card|
  bank_transfer|cheque|online), **amount**, **applied_amount**, currency, **received_at**,
  reference, created_by, soft deletes.
  `amount` is the cash actually received; `applied_amount` is the portion that settles this
  invoice. They differ only on an overpayment, where the excess becomes a customer credit.
  `collected_mtd` and the dashboard cash series sum `amount` (real cash);
  `SalesService::recalcInvoice` sums `applied_amount` + applied credits (settlement). Keeping
  the two separate is what makes the derivation immune to a later invoice edit — capping
  `amount_paid` instead would double-count the excess if the invoice were edited upward.
- **customer_credits** — company_id, credit_no, customer_id,
  source(overpayment|invoice_adjustment|manual), source_invoice_id (nullable),
  **source_payment_id** (nullable), currency, **amount**, **applied_amount**,
  status(open|applied|void), reason, created_by, issued_at, soft deletes;
  unique (company_id, credit_no). `remaining` = amount − applied_amount (accessor).
  `source_payment_id` is what lets `deletePayment` reverse the credit its payment created —
  unapplied it is voided with the payment, already applied the delete is refused.
- **credit_applications** — company_id, customer_credit_id, invoice_id, **amount**,
  applied_at, created_by. The ledger of how a credit was consumed. Deliberately **not** a
  `payments` row: applying a credit moves no new cash, so recording it as a payment would
  double-count the original overpayment in `collected_mtd`.
- **price_books** (Zoho gap #7) — company_id, name, currency(char3), description, is_active,
  valid_from, valid_to, soft deletes
- **price_book_entries** — company_id, price_book_id, product_id, unit_price (absolute);
  unique (price_book_id, product_id)
- attachment points (all nullable): **customers.price_book_id**, **customer_groups.price_book_id**
  (customer's own book wins, else the group's — `Customer::effectivePriceBookId()`), and the
  provenance column on the three sales headers above. Pricing is **convenience/suggest-only**: a
  resolver suggests a line's `unit_price`; the write paths never enforce it. See
  `docs/PRICE_BOOKS_SCOPE.md`.

### Forecasts (Zoho gap #10)
- **sales_targets** — company_id, user_id, period_type (`month|quarter`), period_start (first day
  of the period), target_amount. `unique(company_id, user_id, period_type, period_start)`. No new
  columns elsewhere — the forecast reads closed-won/pipeline live off `deals` (`won_at`,
  `expected_close_date`, `amount`, `probability`, `owner_id`). See `docs/FORECASTS_SCOPE.md`.

### Manufacturing / BOM (Enterprise-only)
- **bom_items** — company_id, product_id (finished good), component_product_id (a component — also
  a product), quantity (**per one unit** of the finished good). `unique(product_id, component_product_id)`.
  Headerless: a product has a BOM iff it has ≥1 row. Cycles prevented at write time.
- **builds** — company_id, build_no, product_id (output), warehouse_id, quantity, unit_cost,
  total_cost (rolled up), status (`completed`), built_by, built_at, soft deletes.
- **build_items** — company_id, build_id, component_product_id, name, quantity (consumed), unit_cost.
- A build consumes components (`consume`) and produces the finished good (`produce`) through the
  signed stock ledger (`InventoryService::applyMovement`), atomically. See `docs/MANUFACTURING_BOM_SCOPE.md`.

**The FK M4 deferred is now closed:** `deal_products.product_id` references `products`.
**Line totals are computed server-side** (`SalesDocumentItem::computeLine`, handling inclusive
tax) and rolled into the header by `SalesDocument::recomputeTotals()`; the client cannot spoof
tax. **Invoice status/amount_paid/balance re-derive from `payments`** on every payment change.
The conversion chain quotation → sales_order → invoice copies header + items and is idempotent
(refuses a second conversion). Column names `invoices.created_by/status/issue_date/grand_total`
and `payments.amount/received_at` match what `DashboardService` reads.

## Module 8 — Purchase (implemented)
- **vendors** — company_id, vendor_no, name, legal_name, email/phone/mobile/website, tax_id,
  currency, payment_terms_days, address, contact_name, status(active|on_hold|blocked), soft deletes
- **purchase_requests** — company_id, pr_no, requested_by, department_id, branch_id,
  status(draft|submitted|approved|rejected|converted|cancelled), needed_by, estimated_total,
  converted_po_id, soft deletes
- **purchase_request_items** — company_id, purchase_request_id, product_id (soft), name,
  quantity, estimated_price, note
- **purchase_orders** — company_id, po_no, vendor_id, purchase_request_id, warehouse_id,
  created_by, **status**(draft|submitted|confirmed|received|closed|cancelled), **order_date**,
  expected_date, currency, subtotal/discount_total/tax_total/**grand_total**, received_at, soft deletes
- **purchase_order_items** — company_id, purchase_order_id, product_id (soft), name, quantity,
  **received_quantity**, unit_price, discount_pct, tax_rate_id, tax_amount, line_total
- **approval_workflows** — company_id, name, document_type(purchase_request|purchase_order),
  min_amount, **approver_ids (ordered JSON)**, is_active
- **approval_requests** — company_id, workflow_id, `approvable` morph, status(pending|approved|
  rejected), current_step, requested_by
- **approval_actions** — company_id, approval_request_id, step, approver_id, action(approve|
  reject), comment, acted_at

**Approval engine is generic.** `ApprovalService` walks a workflow's ordered `approver_ids`
one step at a time; only the current step's approver may act; the last approval resolves to
approved, any rejection to rejected. `PurchaseService::actOnApproval` reflects the outcome on
the document (PR → approved/rejected, PO → confirmed/back-to-draft). Submitting a PR / confirming
a PO opens an approval only if a workflow's `min_amount` ≤ the total; otherwise it auto-advances.
**Receiving a PO posts `purchase` stock movements** into `warehouse_id` via
`InventoryService::receive()` and tracks `received_quantity` per line. Dashboard purchase chart
reads `purchase_orders.order_date` + status in (confirmed|received|closed) + `grand_total`.

## Module 9 — Inventory (implemented)
- **warehouses** — company_id, name, code, branch_id, address, contact, is_default, is_active
- **stock_items** — company_id, product_id, warehouse_id, quantity (on hand), reserved_quantity,
  average_cost, bin_location; unique(product_id, warehouse_id)
- **stock_movements** — company_id, product_id, warehouse_id, type(receipt|issue|adjustment|
  transfer_in|transfer_out|sale|purchase), **signed quantity**, **balance_after**, unit_cost,
  reference, nullable `related` morph, user_id, occurred_at
- **stock_transfers** — company_id, transfer_no, from/to_warehouse_id, status(draft|in_transit|
  received|cancelled), transfer_date, requested_by, shipped_at, received_at, soft deletes
- **stock_transfer_items** — company_id, stock_transfer_id, product_id, name, quantity
- **barcodes** — company_id, product_id, barcode (unique per company), type, is_primary

**On-hand is a running balance** kept in `stock_items.quantity`; every change funnels through
`InventoryService::applyMovement()`, which upserts the stock row and writes a ledger entry with
the signed delta and resulting `balance_after`, so stock and ledger always reconcile. Transfers
move nothing until shipped: **draft → in_transit** issues `transfer_out` from the source,
**in_transit → received** posts `transfer_in` to the destination; cancelling a shipped transfer
returns the goods. Reorder level lives on `products` (from M7); `StockItem::scopeLowStock` is a
correlated EXISTS comparing on-hand to it.

## Module 10 — Helpdesk (implemented)
- **ticket_categories** — company_id, name, code, is_active
- **sla_policies** — company_id, name, priority (unique per company), first_response_minutes,
  resolution_minutes, is_active
- **tickets** — company_id, ticket_no, subject, description, **status**(new|open|pending|
  resolved|closed), priority(low|medium|high|urgent), category_id, customer_id, requester_name/
  email, channel, assigned_to, created_by, sla_policy_id, first_response_due_at, **due_at**,
  first_response_at, resolved_at, closed_at, reopened_count, soft deletes
- **ticket_replies** — company_id, ticket_id, user_id, author_type(agent|customer|system),
  is_internal (note vs public reply), body
- **escalations** — company_id, ticket_id, level, reason, escalated_to, escalated_by, note, escalated_at

### Knowledge Base (Zoho gap #8, module `kb`)
- **kb_categories** — company_id, name, code, is_active. unique(company_id, code)
- **kb_articles** — company_id, category_id, title, slug (unique per company), body (**plain
  text**), excerpt, status(draft|published), visibility(internal|public), view_count, author_id,
  published_at, soft deletes. index (company_id, status, visibility) — the portal double-gate.
  Portal exposure = published **AND** public, company-wide (no customer_id); see
  `docs/KB_SCOPE.md`.

### Documents (Zoho gap #9)
- **document_folders** — company_id, name, created_by. Flat (no nesting).
- **documents** — company_id, folder_id (nullable, nullOnDelete → unfiled), name, description,
  disk (always private `local`), path (hashed, company-scoped), mime, size, uploaded_by. **No soft
  deletes** (delete removes row + file). Separate from per-record `attachments`; files are private
  with authenticated download only — **no public URL**. See `docs/DOCUMENTS_SCOPE.md`.

**Column contract:** `tickets.status` (new|open|pending) and `tickets.due_at` are read by
`DashboardService` for the open-tickets and SLA-breaching KPIs. `HelpdeskService::applySla`
picks the active policy matching the ticket's priority and stamps deadlines from `created_at`;
changing priority (including on escalation) re-derives them. The first public agent reply stamps
`first_response_at` and opens a `new` ticket; a customer reply on a resolved/closed ticket
reopens it and bumps `reopened_count`.

## Module 11 — Marketing (implemented)
- **sms_providers** — company_id, name, provider(twilio|nexmo|unifonic|generic), sender_id,
  is_default, is_active
- **campaigns** — company_id, name, type(email|sms), status(draft|scheduled|running|sent|
  paused|cancelled), subject, body, email_template_id, email_account_id, sms_provider_id,
  **audience (JSON: {source: customers|leads, filters})**, scheduled_at, sent_at, counters
  (recipients/sent/opened/clicked/failed), created_by, soft deletes
- **campaign_recipients** — company_id, campaign_id, `recipient` morph (customer/lead), name,
  email, phone, status(pending|sent|opened|clicked|bounced|failed), sent_at, opened_at
- **campaign_messages** — company_id, campaign_id, campaign_recipient_id, channel, to_address,
  subject, body, status, message_id, sent_at

**Structural send, like Email/chat outbound.** `MarketingService::queryAudience` resolves the
audience spec into contacts (customers by status/group, or open leads by rating/status);
`previewAudience` counts total vs reachable (having email for email, phone for SMS) before
launch. **Launch** materialises one `campaign_recipient` + `campaign_message` per reachable
contact, marks them sent, and rolls counts up — no live provider is contacted. Reuses M6
`email_templates` for content. Campaigns are a CEO/admin function (no dedicated marketing role).

## Module 12 — HR (implemented)
- **employees** — company_id, employee_no, user_id (self-service link), first/last_name, email,
  phone, department_id, branch_id, manager_id (self-ref), job_title, employment_type(full_time|
  part_time|contract|intern), status(active|on_leave|terminated), hire_date, date_of_birth,
  national_id, salary, currency, address, emergency_contact, soft deletes
- **leave_types** — company_id, name, code, days_per_year (entitlement), is_paid, color, is_active
- **attendances** — company_id, employee_id, date, check_in, check_out, status(present|absent|
  late|half_day|leave|holiday|weekend), hours_worked, notes; unique(employee_id, date)
- **leave_requests** — company_id, employee_id, leave_type_id, start/end_date, days, reason,
  status(pending|approved|rejected|cancelled), approved_by, approved_at, decision_note, soft deletes
- **leave_balances** — company_id, employee_id, leave_type_id, year, entitled, used; unique per
  (employee, type, year); `remaining` is derived

**Leave flow.** Creating a request computes its inclusive-day span and is refused (422) if the
employee's balance for that type/year is insufficient. Approving deducts the days from the
balance and stamps `leave` attendance rows across the span; cancelling an approved request
returns the days. New employees get a current-year balance row per active leave type
(`ensureBalances`). Attendance logging computes `hours_worked` from the clock times.

## Module 13 — Reports (implemented)
- **saved_reports** — company_id, name, description, dataset (registry key), dimension,
  measures (JSON), filters (JSON), chart_type(table|bar|line|pie), is_shared, created_by, soft deletes
- **dashboards** — company_id, name, layout (JSON: [{report_id, size}]), is_default, created_by
- **report_exports** — company_id, saved_report_id, format(csv|pdf|xlsx), status, disk, path,
  row_count, requested_by, generated_at

**Whitelisted query engine.** `ReportService::registry()` defines datasets (deals/invoices/
leads/tickets), each with dimensions, measures and filters as **trusted SQL fragments**; the
user only supplies *keys* validated against the registry, and all filter values are bound — no
raw SQL is accepted. `run()` builds a grouped query (dimension + chosen measures, optional join
per dimension, date-range + whitelisted filters), capped at 1000 rows. Saved reports persist a
spec; **export** runs it and writes a real CSV to the `public` disk, recording a `report_exports`
row. Dashboards compose saved-report widgets.

## Module 14 — Workflow (implemented)
- **workflows** — company_id, name, description, entity(leads|deals|tickets|customers|invoices),
  trigger_type(manual|event|schedule), trigger_event, conditions (JSON [{field,op,value}] ANDed),
  schedule_cron, is_active, run_count, last_run_at, created_by, soft deletes
- **workflow_actions** — company_id, workflow_id, order, type(create_task|update_field|send_email|
  notify|webhook|log), config (JSON)
- **workflow_runs** — company_id, workflow_id, trigger_type, status(success|partial|failed|
  skipped), `subject` morph, log (JSON per-action results), actions_run, started/finished_at,
  triggered_by
- **scheduled_jobs** — company_id, workflow_id, name, cron, is_active, next_run_at, last_run_at

**Trigger → conditions → ordered actions engine.** `WorkflowService::run` optionally resolves a
subject record of the workflow's entity, evaluates the conditions against it (skipped run if they
fail), then executes each action in order, logging per-action success/failure. `create_task`,
`update_field` (whitelisted per entity) and `send_email` have real side effects; `notify`/
`webhook`/`log` are structural. Runs can be triggered manually via the API; event/schedule
triggers dispatch to the same `run()` (wiring left for later). Every run is recorded.

## Module 15 — AI Assistant (implemented)
- **ai_conversations** — company_id, user_id, title, soft deletes
- **ai_messages** — company_id, ai_conversation_id, role(user|assistant), content, meta (data
  the answer was built from)
- **ai_insights** — company_id, type, level(info|warning|critical), title, body, meta,
  is_dismissed, generated_at
- **ai_predictions** — company_id, type(pipeline_forecast|top_lead|churn_risk), `subject` morph,
  title, value (JSON: score/amount/probability + factors), generated_at

**Structural, no live LLM.** `AiService::respond()` is a heuristic that keys off the message
text and answers from the company's real data (pipeline & top deals, hot leads, overdue
invoices, ticket/SLA load, a business summary) — swap it for a real model later. Insights are
regenerated from live data (overdue invoices, stale deals, unassigned hot leads, SLA breaches);
predictions compute a weighted pipeline forecast, the top lead by score, and a churn signal from
overdue balances. **This lights up the dashboard `ai-insights` widget** (`DashboardService::
aiInsights()` now reads `ai_insights` — the app's last placeholder is gone).

## Module 16 — Social / Chat Inbox (implemented)
Unified inbox for WhatsApp, Messenger, Instagram, TikTok, Telegram, SMS and website live chat.
Channel-agnostic core: adding a provider needs no schema change.

**Many accounts per provider.** A company typically runs several (5 Facebook pages, 3 Instagram
profiles, 2 Telegram bots…). Each is its own `chat_channels` row; the API filters by
`channel_id` for one account or `channel_type` to roll up every account of a provider.
- **chat_channels** — company_id, type(whatsapp|messenger|instagram|tiktok|telegram|sms|webchat),
  name, external_account_id, config(encrypted JSON credentials), is_active.
  Two unique indexes: `(company_id, type, external_account_id)` and `(company_id, type, name)`.
  The second one carries the weight — `external_account_id` is legitimately NULL for
  webchat/sms, and MySQL permits unlimited NULLs in a unique index, so that index alone
  would not stop duplicates.
- **chat_contacts** — company_id, channel_id, external_user_id, display_name, avatar_url,
  phone, email, linked_type/linked_id; unique(channel_id, external_user_id).
  `linked_*` points at a Lead (M2) or Customer (M3) and is deliberately **unconstrained** —
  those tables ship later; same polymorphic pattern as `addresses`.
- **chat_conversations** — company_id, channel_id, contact_id, assigned_to, external_thread_id,
  subject, status(open|pending|snoozed|resolved|closed), priority, tags(JSON),
  last_message_preview, last_message_at, **last_inbound_at**, unread_count, soft deletes.
  `last_inbound_at` drives Meta's 24-hour service window (see below).
- **chat_messages** — company_id, conversation_id, user_id(agent), direction(inbound|outbound|note),
  content_type, body, external_message_id, status(queued|sent|delivered|read|failed), error, meta(JSON), sent_at
- **chat_message_attachments** — message_id, disk, path, thumbnail_path, name, mime, size,
  width, height, duration_seconds. Media (image/video/audio) and PDF, stored on the `public`
  disk under `chat/YYYY/MM/`. Accepted mimes are whitelisted and each file is capped at 15 MB —
  well below PHP's 64 MB ceiling, because a 64 MB upload through this stack times out before
  completing and surfaces as a network error rather than a validation message.
- **chat_canned_responses** — company_id, shortcut, title, body; unique(company_id, shortcut)

**24-hour service window.** WhatsApp/Messenger/Instagram only permit free-form replies within
24h of the last inbound message; outside it, only pre-approved templates. `ChatConversation::serviceWindowOpen()`
enforces this — the API returns 422 rather than queueing a message the provider would reject.
Internal notes (`direction=note`) are exempt, and channels without the rule are always open.

Indexes: `(company_id, status, last_message_at)` and `(assigned_to, status)` on conversations;
`(conversation_id, created_at)` on messages.

## Module 17 — Dynamics 365 Business Central integration (structure)
Sync framework between the CRM and Microsoft Dynamics 365 Business Central.
Built on `Illuminate\Http` (Guzzle) — **no new Composer dependency**.

> **Never verified against a live tenant.** The OAuth and OData shapes follow
> Microsoft's documented API and the error path is exercised against the real
> Entra ID endpoint, but no call has ever succeeded against an actual BC
> environment. Region hosts and entity shapes still need confirming.

- **bc_connections** — company_id, name, environment, tenant_id, client_id,
  client_secret(encrypted, nullable), bc_company_id, bc_company_name, base_url,
  api_version, is_active, status(unconfigured|ok|auth_failed|unreachable),
  last_connected_at, last_error; unique(company_id, name).
  `client_secret` is nullable **on purpose**: the documented path is
  `DYNAMICS_CLIENT_SECRET` in `.env`, since a DB dump plus `APP_KEY` would
  otherwise be a full credential compromise. DB storage is the multi-tenant fallback.
- **bc_entity_mappings** — connection_id, crm_entity, bc_entity, direction(pull|push|
  bidirectional), is_enabled, field_map(JSON), filter(JSON), sync_cursor,
  interval_minutes, last_run_at; unique(connection_id, crm_entity, bc_entity).
  `crm_entity` is a string key, not an FK — most CRM modules ship later.
- **bc_record_links** — the crosswalk that makes sync idempotent. connection_id,
  mapping_id, crm_type, crm_id, **bc_entity**, bc_id, bc_etag, payload_hash,
  last_direction, last_synced_at. Two unique keys:
  `(connection_id, crm_type, crm_id)` and `(connection_id, bc_entity, bc_id)`.
  The BC side **must** include the entity set — BC GUIDs are unique per set, so a
  customer and a vendor id can otherwise collide.
- **bc_sync_runs** — connection_id, mapping_id, direction, trigger(manual|scheduled|
  webhook), status(queued|running|success|partial|failed), created/updated/skipped/
  failed counts, started_at, finished_at, error
- **bc_sync_issues** — run_id, record_link_id, stage(auth|fetch|map|write|conflict),
  severity(warning|error|conflict), crm_type/crm_id, bc_id, message, context(JSON), resolved_at
- **bc_webhook_subscriptions** — connection_id, resource, subscription_id,
  notification_url, client_state(encrypted), expires_at, last_renewed_at

**Optimistic concurrency.** BC requires `If-Match` with the stored `bc_etag` on PATCH.
A 412 is a conflict recorded in `bc_sync_issues`, not a transient failure to retry blindly.

## Indexing
- FKs auto-indexed. Composite `(company_id, status, created_at)` on hot tables (deals, leads, tickets).
- Full-text on customers.name, leads.name, tickets.subject for global search.
- Redis caches all lookup tables.
