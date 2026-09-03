# Manufacturing / Bill of Materials (Phase B)

**Status:** decisions settled, **being built** (module `manufacturing`). The "Production
information = Manufacturing/BOM" piece the owner confirmed; kept out of the product-creation form
(Phase A: [`INVENTORY_PRODUCT_SCOPE.md`](INVENTORY_PRODUCT_SCOPE.md)).

**Decisions locked (product owner, 2026-08-31):**
- **Immediate build** — one action consumes components + produces the finished good in a single
  atomic step, recording a build record. No draft/in-progress/reservation state machine.
- **Hard-refuse on short stock** — if any component's on-hand at the build warehouse is below
  `per_unit × build_qty`, reject with a 422 naming the short component. **No negative stock.**
- **Enterprise-only** plan tier (`feature:manufacturing`; enterprise = all modules, so it's
  included there and nowhere else).
- **Headerless BOM (one per product):** component lines live directly on the finished-good product;
  no BOM header/versioning for MVP (additive later).

## What it is
A **finished-good** product is assembled from **component** products in fixed quantities (its bill
of materials); a **build** operation consumes the components from stock and produces the finished
good — all through the existing signed stock ledger.

## Data model (settled; migration 000041)
- **bom_items** — company_id, product_id (the finished good), component_product_id, quantity
  (**per one unit** of the finished good), timestamps. `unique(product_id, component_product_id)`.
  A product "has a BOM" iff it has ≥1 bom_item — no separate header (headerless MVP).
- **builds** — company_id, build_no, product_id (output), warehouse_id, quantity (units produced),
  unit_cost + total_cost (rolled up), status (`completed` in immediate mode; column kept for a
  future cancel), notes, built_by, built_at, timestamps, softDeletes.
- **build_items** — company_id, build_id, component_product_id, name (denormalised), quantity
  (consumed = per_unit × build qty), unit_cost, timestamps.

## How a build runs (immediate, atomic)
One `DB::transaction`:
1. Require the finished good to have a BOM (≥1 bom_item), else 422.
2. For each component: `required = per_unit × build_qty`; read on-hand at the warehouse **under the
   same row lock `applyMovement` takes**. If any component is short → throw → **422 naming it**,
   whole build rolled back (no partial consumption).
3. `applyMovement(component, warehouse, 'consume', -required)` for each, then
   `applyMovement(product, warehouse, 'produce', +build_qty, unit_cost=rolled-up)`. Every stock
   change goes through the **one row-locked ledger writer** — never a direct `stock_items` write.
4. **Cost roll-up:** finished-good unit cost = Σ(component per-unit cost × per_unit), where component
   cost = its `stock_items.average_cost` at that warehouse (else `products.cost_price`). Passed as
   `unit_cost` to the produce movement, so `applyMovement` sets the finished good's average_cost.
5. Record the `builds` + `build_items` snapshot.

New ledger movement types **`consume`** / **`produce`** (the `type` column is a free string — no
enum migration); the Stock Movements UI gets locale + badge styling for them.

## BOM validation (cycle prevention)
Reject `component_product_id == product_id` outright, and run a bounded DFS over the transitive
component tree when saving a BOM to reject any cycle (a component whose own BOM reaches the finished
good). Depth-capped as a backstop.

## Endpoints (`feature:manufacturing`, Enterprise)
- `GET /manufacturing/boms` (manufacturable products), `GET /manufacturing/meta` (products +
  warehouses) — `manufacturing.view`
- `GET /manufacturing/products/{id}/bom` — `manufacturing.view`
- `PUT /manufacturing/products/{id}/bom` (replace lines) — `manufacturing.manage`
- `GET /manufacturing/products/{id}/availability?warehouse_id&quantity` (buildable? what's short) —
  `manufacturing.view`
- `GET /manufacturing/builds`, `GET /manufacturing/builds/{id}` — `manufacturing.view`
- `POST /manufacturing/builds` `{product_id, warehouse_id, quantity, notes?}` — `manufacturing.build`

## RBAC (three TenantProvisioner places)
- `MODULES`: `manufacturing => [view, manage, build]`.
- `roleDefinitions`: Warehouse full; Owner/CEO via existing rules.
- `planDefinitions`: **not** added to Professional — enterprise already = all modules, so it lands
  there only.

## Guardrails (same as everything else here)
Company-scope every `exists:` rule; `catch(QueryException)` before `catch(RuntimeException)`; one
transaction per build; verify with the query-count harness. Route **all** stock changes through
`applyMovement` — never write `stock_items`/`stock_movements` directly.
