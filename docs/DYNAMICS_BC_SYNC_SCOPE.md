# Dynamics 365 Business Central — entity syncers (M17)

**Status:** decisions settled, **built** (pull-only). Closes the long-standing
"only `CompanySyncer` exists" gap. The connection/mapping/run infrastructure already existed
and is unchanged — this adds the three CRM-side syncers those tables were built for.

## The constraint that shapes everything

**There is no Business Central tenant available to this project.** The client's own docblock has
said so since it was written: *"no request in this class has ever completed successfully against
a live BC tenant."* That has not changed, and shipping syncers does not change it.

What that means in practice, stated plainly so nobody has to guess later:

- **Verified:** the sync *logic* — field mapping, business-key matching, the crosswalk, idempotency
  via `payload_hash`, incremental cursor handling, run counters, issue logging, connection-status
  transitions, paging, and every error path. All exercised against a controlled OData stub.
- **NOT verified:** BC's real field names, entity-set shapes, OData dialect quirks, region/environment
  host differences, and Entra ID auth. A stub answers whatever it is told to answer.

So the first run against a real tenant should still be treated as a first run. The likely failure
is a field name, not the engine.

## Decisions locked

### 1. Pull only (BC → CRM). No push.
`supportedDirections()` returns `['pull']` on all three syncers, so configuring a `push` or
`bidirectional` mapping fails loudly through the engine's existing direction check rather than
silently no-opping.

Why: push writes into a customer's live ERP. With no tenant to verify against, a field-mapping
mistake corrupts real accounting data in a system Krama does not own. Pull's worst case is visible
junk rows in the CRM, which an operator can see and delete. Push is additive later, once someone
has a sandbox tenant to prove it against.

### 2. Invoices are **link-only reconciliation** — never created.
`InvoiceSyncer` matches BC `salesInvoices` to invoices that **already exist** in the CRM (by
number) and records the crosswalk. It does **not** create, update or post any financial row.
Unmatched BC invoices are logged as `BcSyncIssue` warnings, which is the useful output: *"these
exist in BC and not here."*

Why: CRM invoice money columns (`amount_paid`, `balance`, `status`) are derived by
`SalesService::recalcInvoice` from payments and applied credits. Materialising invoices from an
external feed would either bypass that derivation or invent payments — the same class of risk that
kept Stripe parked. Reconciliation gives most of the value with none of it.

### 3. Syncers write through the **model**, with every column supplied — not through the services.
`RunBcSync` runs on the `integrations` queue, i.e. **unauthenticated**. `CustomerService` and
`ProductService` derive their business key with `auth()->user()?->company_id`
(`nextCustomerNo()` / `nextSku()`), which is null there — every pulled record would be numbered
`CUST-00001` and collide on the unique index.

BC owns the number anyway (`number` → `customer_no` / `sku`), so there is nothing to generate.
Writing via the model with an explicit `company_id` also satisfies `BelongsToCompany`'s `creating`
hook (`if (!$model->company_id && auth()->check())`) and avoids the service-layer `fireEvent()` /
`TimelineActivity::record()` paths, which also read `auth()`.

Consequence, accepted: a pulled customer does **not** raise `customer.created`, so workflows do not
fire for it. That is the safer default — an import of 5,000 customers should not trigger 5,000
workflow runs — and it is recorded here rather than discovered.

### 4. Matching order (per row): crosswalk → business key → create.
1. `BcRecordLink` on (`connection_id`, `bc_entity`, `bc_id`) — the authoritative link once it exists.
2. Otherwise a CRM row in the same company with the same business key (`customer_no` / `sku`),
   which adopts pre-existing records instead of duplicating them on the first sync.
3. Otherwise create.

**Never matched on name** — names are not unique and a wrong match silently merges two accounts.

### 5. Idempotency: `payload_hash` over the mapped subset.
A row whose hash is unchanged counts as `skipped` and is not written. Same pattern as
`CompanySyncer`. This is what makes re-running a sync cheap and non-destructive.

### 6. Incremental: `sync_cursor` as a high-water mark on `lastModifiedDateTime`.
- Sent as an OData `$filter` when the cursor is set.
- Advanced **only when `failed === 0`** — advancing past a partial run would permanently skip the
  rows that failed.
- Set to the **maximum `lastModifiedDateTime` actually seen**, never `now()` — using the clock loses
  anything modified while the run was in flight.

### 7. Field-map override.
`bc_entity_mappings.field_map` (`crm_field => bc_field`) overrides the defaults per mapping, so a
tenant whose BC has custom field names can be corrected without a code change. Unknown CRM fields
in the map are ignored rather than written blindly.

## Mapping defaults

| CRM (customers) | BC (`customers`) |
|---|---|
| `customer_no` | `number` |
| `name` | `displayName` |
| `email` | `email` |
| `phone` | `phoneNumber` |
| `website` | `website` |
| `tax_id` | `taxRegistrationNumber` |
| `currency` | `currencyCode` |
| `payment_terms_days` | *(derived from `paymentTermsId` — not mapped; BC exposes an id, not days)* |
| `status` | `blocked` → non-empty means `blocked`, else `active` |

| CRM (products) | BC (`items`) |
|---|---|
| `sku` | `number` |
| `name` | `displayName` |
| `unit` | `baseUnitOfMeasureCode` |
| `cost_price` | `unitCost` |
| `sale_price` | `unitPrice` |
| `barcode` | `gtin` |
| `type` | `type` → BC `Inventory` maps to `goods`, anything else to `service` |
| `is_active` | `blocked` → inverted |

| CRM (invoices) | BC (`salesInvoices`) |
|---|---|
| *(match only)* `invoice_no` | `number` |

Deliberately **not** mapped: `owner_id`, `branch_id`, `group_id`, `price_book_id`, `category_id`,
`tax_rate_id`, `credit_limit`. These are CRM-side organisational choices with no BC equivalent, and
guessing them would overwrite decisions a user made in Krama.

## Not built (additive later, named so the gap stays visible)
- **Push / bidirectional** — see decision 1.
- **Invoice creation from BC** — see decision 2.
- **Contacts, vendors, sales orders, purchase orders** — `SyncEngine::SUPPORTED_BC_ENTITIES` still
  lists them for mapping configuration; attempting one fails loudly with "No syncer implemented".
- **Delete/archive propagation** — a customer removed in BC is left alone in the CRM. Deleting CRM
  records from an external feed needs an explicit product decision.
- **Scheduled runs** — `bc_entity_mappings.interval_minutes` exists and `RunBcSync` is queueable,
  but nothing schedules them yet; runs are triggered from the UI.
