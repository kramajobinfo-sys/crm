# Price Books (Zoho gap #7) — scope of work

**Status:** scoping only, decisions settled (see §1). Not built. This is a normal module build (see
the "Definition of done, per module" checklist in [`BUILD_PROGRESS.md`](BUILD_PROGRESS.md)), not an
architecture change, so this doc is short: the deltas from today's code and the constraints that are
easy to get wrong.

**Decisions locked (product owner, 2026-08-31):**
- **Convenience, suggest-only** — server resolves a suggested price the UI pre-fills; the line
  editor still accepts any `unit_price`. `syncItems()`'s trust model is **unchanged**. No override
  permission, no override audit trail.
- **Absolute `unit_price`** entries (not discount-off-list).
- **Professional+** plan tier (`feature:price_books` on Professional and Enterprise, not Starter).

**Goal:** per-customer / per-segment / per-currency price lists, so a product's selling price on a
quote/order/invoice comes from a price book instead of a single global `products.sale_price`.

---

## 0. How pricing works today (the baseline to delta from)

- `products` has a **single** `cost_price` + `sale_price` (`Product.php`), **no currency column**.
- A sales line's `unit_price` is **taken verbatim from the client** —
  [`SalesService::syncItems()`](../backend/app/Services/SalesService.php) line 368:
  `$price = (float) ($line['unit_price'] ?? 0);`. The server resolves **tax** server-side (can't
  be spoofed) but **not price**.
- Quote→order→invoice **copies `unit_price` verbatim** (`copyItems()`), and `copyHeader()` copies
  `currency` but nothing pricing-related.
- Currency is a **label only** — there is no FX/exchange-rate table anywhere; `Payment.currency`
  just inherits `$invoice->currency`, never converts. `Customer` already has a `currency` column and
  a `group_id` (`CustomerGroup`), and an `effectivePaymentTerms()` "own value, else group's"
  fallback that price-book resolution should mirror.
- Sales headers are **three separate tables** (`quotations`, `sales_orders`, `invoices`), each with
  its own items table; the shared `Schema::create` helper in migration `000014` is already spent, so
  any header column is an **ALTER across all three**.

---

## 1. Decision — *convenience*, settled

Client-supplied price is **not a bug**: in B2B reps negotiate, and Zoho itself lets you edit a line
price off the book. Decision: **convenience / suggest-only**.

- Server resolves a **suggested** price the UI pre-fills; the line editor still accepts any
  `unit_price`. **`syncItems()` is left unchanged** — no new server-side enforcement, no
  `products.override_price` permission, no per-line override audit trail.
- Entries hold an **absolute `unit_price`** (not a discount off list). A product with no entry in the
  chosen book falls through the precedence chain (§3) to `products.sale_price`.

Consequence for the build: resolution is a **read path only** (a `GET`-style "what price would this
product be for this customer/book" the line editor calls), plus the CRUD for books/entries. The
existing quote/order/invoice write paths do **not** change.

---

## 2. Constraints that are easy to get wrong

- **Prices must freeze at line creation.** `copyItems()` copying `unit_price` verbatim on
  conversion is **correct and must stay** — a signed quotation's price is binding (e-signature
  shipped). A price book edited later must **never** retroactively change an existing line.
  Therefore: resolution runs **only on new line entry**; conversion paths stay verbatim; a header
  `price_book_id` is **provenance** (what was used), not a re-resolution trigger.
  - Decision to state: should `copyHeader()` carry `price_book_id` forward? Carrying it is right for
    "add a new line to the converted order later" (that line resolves against the same book);
    existing copied lines are untouched regardless.
- **Currency ambiguity in the fallback.** A book is per-currency, but `sale_price` has no currency.
  So: (a) declare by convention that `products.sale_price` is in the **company base currency**, and
  (b) make a **currency mismatch between the document and the chosen price book a hard 422**, never
  a silent fall-through to `sale_price`. Per-currency books thus become the app's **only real
  multi-currency pricing mechanism** — a genuine benefit worth naming.

---

## 3. Data model (delta)

New tables (both `BelongsToCompany` + `SoftDeletes`, `company_id` explicit in seeders):

- **`price_books`** — `id, company_id, name, currency(char3), description?, is_active,
  valid_from?, valid_to?, timestamps, deleted_at`.
- **`price_book_entries`** — `id, company_id, price_book_id(FK), product_id(FK), unit_price?,
  discount_pct?, timestamps`. Unique `(price_book_id, product_id)`. (Quantity-tiered / "differential"
  pricing via a `min_quantity` break is **explicitly out of MVP scope** — a phase-2 add.)

New FK columns (nullable, so every existing record and pipeline is unchanged):

- `customers.price_book_id` and `customer_groups.price_book_id` — the attachment points.
- `price_book_id` on **`quotations`, `sales_orders`, `invoices`** (the ALTER-across-three noted in §0).

**Resolution precedence** (mirrors `effectivePaymentTerms()`): explicit line override (if §1 = A, or
§1 = B with permission) → document `price_book_id` → customer `price_book_id` → customer's group
`price_book_id` → `products.sale_price` (base currency, subject to the §2 currency-match rule).

---

## 4. Build surface (standard module checklist)

- **Migration:** the two new tables + the four ALTER columns (one migration, next number after 000033).
- **Models:** `PriceBook`, `PriceBookEntry`; relations on `Customer`, `CustomerGroup`, and the three
  sales docs; a `Product::priceIn($priceBookId, $currency)` or a dedicated resolver service.
- **Resolver:** a single method taking the resolved `price_book_id` + the line's `product_id`s and
  returning prices **in one batched query** keyed by `product_id` — do **not** query per line.
  `syncItems()` already pre-loads product tax rates in one query (lines 360–361); follow that shape.
  This resolver is a **read path** the line editor calls to pre-fill prices; the write paths
  (`syncItems()` / `createInvoice` / conversions) are **not touched** (§1 = convenience).
- **Service/controller/routes:** `PriceBookController` (CRUD + entries) plus a lightweight resolve
  endpoint for the line editor, gated by a new `price_books.*` permission and `feature:price_books`.
- **API Resources, FormRequests, permissions, seeder, Vue page, en/ar locale strings, docs** — per
  the standard checklist.

---

## 5. Do not reintroduce the bugs the 2026-08-31 audit just fixed

- New FormRequests will use `exists:price_books,id` / `exists:products,id` — the audit found **4
  `exists:users,id` rules missing the company scope** (cross-tenant enumeration). **Company-scope
  every new `exists` rule from the start.**
- Any new controller `catch (RuntimeException)` must be preceded by
  `catch (QueryException $e) { throw $e; }` **first** — all 30 existing sites are guarded; do not add
  site 31 unguarded (raw SQL leaked as a 422 otherwise).
- Verify the resolver's query count with the **in-process query-count harness** (the project's only
  regression net) — a per-line price lookup is an N+1 by construction.

## 6. Three-place permission change + standing gotchas

A new `price_books` module touches **three** places in
[`TenantProvisioner`](../backend/app/Services/TenantProvisioner.php), all required:
1. `MODULES` — the permission vocabulary (`price_books.view/create/update/delete`).
2. `roleDefinitions()` — which roles get it (Owner/CEO, Sales Manager; Accountant view).
3. `planDefinitions()` — **which tier** includes it, since `feature:price_books` gates the route
   group. (Recommend Professional+; it's a B2B feature.)

Standing gotchas that apply: **log out / back in** before testing the UI (permissions bake into the
auth store at login); `migrate`, never `migrate:fresh`; clear the Vite cache if the new page 504s.

---

## Recommended sequence

1. Migration (two tables + four ALTER columns) + models + batched resolver, verified against the
   query-count harness.
2. CRUD controller/routes/resources/FormRequests (audit-safe per §5) + the resolve endpoint;
   permissions in all three `TenantProvisioner` places (tier = Professional+); seeder.
3. Vue Price Books page + wire the resolver into the quote/order/invoice line editor to **pre-fill**
   `unit_price` (still editable); en/ar strings.
4. Verify with curl, then the browser; commit.

(§1 is settled — convenience/suggest-only — so no change to `syncItems()` or the write paths.)
