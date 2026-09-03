# Product management in Inventory (+ suppliers, hierarchy, opening stock) — scope

**Status:** scoping, decisions settled. Requested 2026-08-31: "create new product should be at
Inventory, with Vendor, Hierarchy (Category/Product Category), Production info, and Stock info."

**Decisions (product owner):**
- **Add product create/edit under Inventory** — one shared backend (`/products`, Sales namespace);
  do **not** duplicate a controller.
  - **Update (2026-09-01):** the owner then asked to **remove the Products tab from Sales**
    (it duplicated the richer Inventory tab). Done — Sales now shows Quotations/Orders/Invoices/
    Payments only. The shared `/products` backend is unchanged (Inventory manages products; the
    Sales line-item picker still reads the catalogue), so this was a frontend-only removal.
- **Multiple suppliers** per product (supplier SKU / cost / lead time; one preferred).
- **Opening stock at create** — optional quantity into a warehouse.
- **"Production information" = Manufacturing / BOM** — confirmed by the owner as a real
  manufacturing capability, **not** part of the product form. Split out as **Phase B** below.

This doc covers **Phase A** (the "create product in Inventory with vendor + hierarchy + stock"
request as asked). Phase B (BOM/manufacturing) is scoped separately in
[`MANUFACTURING_BOM_SCOPE.md`](MANUFACTURING_BOM_SCOPE.md) and built after A.

---

## Current state (baseline)
- Products live in **Sales** (`/products`, `ProductController` in the `Sales` namespace) — full CRUD
  already exists. The **Inventory** module has no product create; it only does stock ops.
- `product_categories` is **already hierarchical** (`parent_id`/`children`), and `storeCategory`
  already accepts `parent_id`. So hierarchy is **UI-only** — no schema work.
- Products have **no vendor link** today (vendors attach only to purchase orders).
- Opening stock isn't settable at create — stock comes in afterward via Inventory receive/adjust.
- `InventoryService::applyMovement` is the **single, row-locked** ledger writer (hardened in the
  audit). `receive()` funnels through it. Opening stock MUST route through it — never write
  `stock_items`/`stock_movements` from the product service.

## Phase A — what gets built

### Data (migration 000039 — Credits took 000038)
- **product_suppliers** — company_id, product_id, vendor_id, supplier_sku?, cost (default 0),
  lead_time_days?, currency?, is_preferred (bool). `unique(product_id, vendor_id)`.
  "Only one preferred per product" enforced in the service (reuse the `createTaxRate` clear-others
  pattern, scoped to the product — not the company).

No other schema: categories already nest; opening stock uses existing inventory tables.

### Backend (extend the shared `/products`, no new controller for CRUD)
- `Product` gains `suppliers()` hasMany + `preferredSupplier()`; new `ProductSupplier` model.
- `StoreProductRequest`/`UpdateProductRequest` accept optional `suppliers[]`
  (`{vendor_id, supplier_sku?, cost?, lead_time_days?, currency?, is_preferred?}`) and, on create,
  optional `opening_stock` (`{warehouse_id, quantity, unit_cost?, bin_location?}`).
- `ProductService::create/update`:
  - sync `suppliers[]` (replace-set, dedupe by vendor_id, enforce single preferred);
  - **opening stock, on create only:** if `track_inventory` and `quantity > 0`, call
    `InventoryService::receive(..., type: 'receipt', note: 'Opening stock')` — **inside the same
    transaction** as the product insert, so a failed movement rolls the product back (no phantom
    stock). **Skipped entirely for services** (`track_inventory = false` → no stock rows).
- `ProductController::meta` adds `vendors` (id/name, active) for the supplier picker.
- `ProductResource` adds `suppliers` + `preferred_supplier`.

### Frontend
- New **Products tab on the Inventory page** (reuses `/products`), with an enriched create/edit
  form in sections: **Product** (sku/name/type/unit/prices/tax) · **Category** (parent → child
  hierarchy pickers, + create-category-with-parent) · **Suppliers** (repeatable vendor rows, mark
  preferred) · **Opening stock** (warehouse + qty + unit cost + bin; hidden for services).
- The existing Sales Products tab stays as-is (it just won't show the new sections unless we choose
  to surface them there too — out of scope for A).
- en + ar strings.

## Guardrails (audit-derived, non-negotiable)
- Every new `exists:` rule (`vendor_id`, `warehouse_id`, `category_id`, `suppliers.*.vendor_id`)
  is **company-scoped** from the start — the class the audit fixed four of.
- Opening stock through the **one** ledger writer; product-create + opening-movement in **one
  transaction**.
- `catch (QueryException)` before `catch (RuntimeException)` at any new catch site.
- Verify the enriched create is not an N+1 (suppliers + category + stock in one save) with the
  query-count harness.

## Plan-coupling note
`/products` is gated `feature:products` (Starter+) while the Inventory page is `feature:inventory`
(Professional+). A Professional tenant has both, so the Inventory Products tab works. A Starter
tenant has products but not the Inventory page — it still manages products from Sales. Managing
products from Inventory also needs the `products.*` **permissions** (e.g. Warehouse role has only
`products.view` today — creating from Inventory needs `products.create`; left to role config, not
changed here).

---

## Phase B — Manufacturing / BOM (separate, built after A)
See [`MANUFACTURING_BOM_SCOPE.md`](MANUFACTURING_BOM_SCOPE.md). In brief: a finished-good product's
bill of materials (component products + quantities) and a build/assembly operation that consumes
components and produces the finished good through the same stock ledger. Module-sized; not part of
the product form.
