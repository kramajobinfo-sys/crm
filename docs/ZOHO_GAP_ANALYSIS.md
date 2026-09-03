# Krama CRM ↔ Zoho CRM — module gap analysis

**Reference:** Zoho CRM's *Sales* application menu structure (as supplied by the product owner,
2026-08-31). **Method:** each Zoho menu item mapped to its Krama equivalent, with the status
verified against the current codebase (not from memory). Naming differs in several places — Zoho
*Accounts* = Krama *Customers*, Zoho *Cases* = Krama *Tickets*, Zoho *Quotes* = Krama *Quotations*.

Status legend: ✅ **Have** · ⚠️ **Partial** · ❌ **Gap**

---

## Sales

| Zoho | Krama | Status | Note |
|---|---|---|---|
| Leads | Leads | ✅ Have | |
| Contacts | Contacts | ✅ Have | person-level record under a Customer |
| Accounts | Customers | ✅ Have | same concept, renamed (org-level record) |
| Deals | Pipeline / Deals | ✅ Have | Kanban + Blueprint-style stage rules |
| Forecasts | Forecasts | ✅ Have (gap #10) | quota per user/period vs. closed-won + weighted pipeline (attainment, gap, forecast). Separate from the AI heuristic on `deals/stats`. See `FORECASTS_SCOPE.md` |
| Documents | Documents | ✅ Have (gap #9) | staff document library on a **private** disk with authenticated download (a security step up from per-record `attachments`, which use the public disk). Versioning / nested folders / portal sharing deferred — see `DOCUMENTS_SCOPE.md` |
| Campaigns | Marketing / Campaigns | ✅ Have | email + SMS |

## Activities

| Zoho | Krama | Status |
|---|---|---|
| Tasks | Tasks | ✅ Have |
| Meetings | Meetings | ✅ Have |
| Calls | Calls | ✅ Have |

## Inventory (Zoho's sales-document group)

| Zoho | Krama | Status | Note |
|---|---|---|---|
| Products | Products | ✅ Have | |
| Price Books | — | ❌ Gap | no price-list model; only `tax_rates` + a single price per product |
| Quotes | Quotations | ✅ Have | + e-signature (ahead of base Zoho) |
| Sales Orders | Sales Orders | ✅ Have | |
| Purchase Orders | Purchase Orders | ✅ Have | |
| Invoices | Invoices | ✅ Have | |
| Vendors | Vendors | ✅ Have | |

## Support

| Zoho | Krama | Status | Note |
|---|---|---|---|
| Cases | Tickets (Helpdesk) | ✅ Have | same concept, renamed; + SLA / escalation |
| Solutions | Knowledge Base | ✅ Have (gap #8) | code name **`kb`**; staff articles + customer-portal help center (published+public). Ticket↔article linkage deliberately deferred — see `KB_SCOPE.md` |

## Integrations

| Zoho | Krama | Status | Note |
|---|---|---|---|
| SalesInbox | Email module | ✅ Have (gap #11) | outbound SMTP + **inbound**: an ingest core (dedupe/match/thread/timeline) fed by a provider-agnostic `POST /emails/inbound` and an IMAP fetcher (webklex; "structure ready" — no IMAP server locally). See `SALESINBOX_SCOPE.md` |
| Social | Social / Chat Inbox | ✅ Have (ahead) | WhatsApp, Messenger, Instagram, TikTok, Telegram, SMS, web chat |
| Visits | — | ❌ Gap | no website visitor / page-view tracking |

## Top-level

| Zoho | Krama | Status | Note |
|---|---|---|---|
| Reports | Reports | ✅ Have | |
| Analytics | Dashboards | ⚠️ Partial | basic BI dashboards, not a full analytics product |
| Agents | AI Assistant | ⚠️ Partial | heuristic assistant, not autonomous Zia-style agents. **NOTE:** confirm whether "Agents" means AI/Zia agents or human sales/support agents (user management) — the latter is already covered by Krama's Users/Roles Settings UI |

---

## Genuine gaps — prioritized build list

Ordered by fit and ascending risk. These are the items added to the Zoho gap-closure queue in
[`BUILD_PROGRESS.md`](BUILD_PROGRESS.md).

1. ~~**Price Books**~~ — **DONE** (queue #7; `docs/PRICE_BOOKS_SCOPE.md`).
2. ~~**Solutions / Knowledge Base**~~ — **DONE** (queue #8, code name `kb`; `docs/KB_SCOPE.md`).
3. ~~**Documents**~~ — **DONE** (queue #9; `docs/DOCUMENTS_SCOPE.md`).
4. ~~**Forecasts**~~ — **DONE** (queue #10; `docs/FORECASTS_SCOPE.md`).
5. ~~**SalesInbox (inbound email)**~~ — **DONE** (queue #11; `docs/SALESINBOX_SCOPE.md`).
6. ~~**Visits**~~ — **DONE** (queue #12, built in a parallel session; `docs/VISITS_SCOPE.md`).

## Where Krama is ahead of Zoho's Sales app

Not gaps — capabilities Krama has that the referenced Zoho Sales menu does not surface: full
**Inventory** (warehouses, signed stock ledger, transfers, barcodes), a real **Purchase** module
(purchase requests + multi-step approval workflows), **HR** (employees, attendance, leave),
multi-channel **Social/Chat inbox**, a **customer self-service portal**, **e-signature**
quote-to-sign, a **workflow engine**, **API keys** for inbound integration, and the
**multi-tenant SaaS** layer (plans, platform console, per-company roles).
