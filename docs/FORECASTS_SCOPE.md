# Forecasts (Zoho gap #10) — scope

**Status:** built. Quota-based sales forecasting — target vs. achieved per user per period.
**Distinct from the AI-prediction heuristic** on `deals/stats` (that estimates; this compares
real quotas to real deal outcomes).

**Design (decided from the deal data, which already carries everything needed):**
- **Achieved / closed** = won deals: `status='won'`, `won_at` within the period, grouped by
  `owner_id`, `SUM(amount)`.
- **Pipeline** = open deals whose `expected_close_date` lands in the period — gross `SUM(amount)`
  and probability-weighted `SUM(amount × probability/100)`.
- **Forecast** = closed + weighted pipeline. **Attainment** = closed / target. **Gap** = target − closed.
- **Target** = a quota per user per period. Period = **month or quarter** (`period_type` +
  `period_start`).
- Amounts summed in raw currency (no FX) — the **same convention** `DealService::stats` already uses.
- **Plan tier: Professional+.** Permissions: `forecasts.view` (see the board + targets),
  `forecasts.manage` (set targets).

## Data (migration 000043)
- **sales_targets** — company_id, user_id, period_type (`month|quarter`), period_start (first day
  of the period), target_amount. `unique(company_id, user_id, period_type, period_start)`.

## Service (`ForecastService`)
- `forecast(type, start)` → one row per user with a target/closed/pipeline this period, plus a
  totals row: target, closed (+won_count), pipeline gross/weighted, forecast, attainment%, gap.
- `targetsForEditing(type, start)` → every active user with their current target (0 default) — the
  editor grid.
- `setTargets(type, start, [{user_id, target_amount}])` → upsert; a 0/absent amount clears that
  user's target.
- **Aggregates filter `company_id` explicitly** (never leaning on `BelongsToCompany`'s global
  scope, which adds no predicate for a platform admin — the class the 2026-08-31 audit fixed).

## Endpoints (`feature:forecasts`)
| Method | Endpoint | Permission |
|---|---|---|
| GET | /forecasts?period_type&period_start | forecasts.view |
| GET | /forecasts/meta | forecasts.view |
| GET | /forecasts/targets?period_type&period_start | forecasts.view |
| PUT | /forecasts/targets | forecasts.manage |

## RBAC
`forecasts.{view,manage}`: Sales Manager view+manage; Sales Staff view; CEO/Owner via existing
rules. Professional+.

## Verified
Service via tinker (Q3: Sales Manager target 750000, closed 141200 from 1 won deal, weighted
pipeline 240200, forecast 381400, attainment 18.8%, gap 608800 — arithmetic confirmed). HTTP:
month/quarter forecast, targets editor lists all active users, PUT saves; **permission split**
(a view-only user gets 200 on GET, 403 on PUT); **plan gate** starter=no, professional/
enterprise=yes.

## Deliberately out of scope (MVP)
Team/company-level targets as first-class rows (the board rolls users up into a totals line
instead); invoiced-revenue basis (deal-based only); multi-currency conversion (raw-sum convention).
