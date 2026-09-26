# Phase 4 — Cache + Dashboard API

> Prereqs: Phase 3 complete. · Follows: Plan §5.2, §7, §11 Phase 4 · Unlocks: Phase 5
> Goal: `PlanPricingCache` (Redis-backed, 24h TTL, write-through invalidation) + `DashboardService` computing the three metrics + the dashboard endpoint.

---

## 1. Scope

**In scope**

- `PlanPricingCache` service (get/put/forget; invalidation wired into plan mutations)
- `DashboardService` (top-5 usage, projected overage revenue, churn risk)
- `GET /api/merchants/{id}/dashboard` endpoint (API-key or team session auth — D4.2)
- Tests incl. edge cases (zero previous month, exactly-at-allowance)

**Out of scope**

- Any UI (Phase 5) · new schema (none)

---

## 2. Tasks

### 2.1 `PlanPricingCache` (`app/Services/PlanPricingCache.php`)

- [x] Key: `plan:{id}:pricing` → `{base_price_cents, included_units, overage_rate_micros, cycle_days}` (Plan §7). — DTO: `App\Data\PlanPricing` (moved out of `Services` — value objects live in `app/Data/`, matching `UserTeam`/`TeamPermissions` convention; `final readonly class`, promoted properties).
- [x] `get(Plan $plan): PlanPricing` — read-through: cache hit → DTO; miss → build from model, `Cache::put` with `config('billing.cache.plan_pricing_ttl')`.
- [x] `forget(int $planId): void` → `Cache::forget`.
- [x] **Write-through invalidation**: `Plan::booted()` model hooks — `saved` (only when pricing-relevant attributes `wasChanged`) and `deleted`. Works regardless of the mutation's caller (controller, seeder, tinker).
- [x] Billing read path: `BillingService` reads pricing through `PlanPricingCache` (single snapshot per plan per run). Phase 3 billing tests unchanged and green; cache-hit behavior covered by the new dashboard tests.

### 2.2 `DashboardService` (`app/Services/DashboardService.php`)

Metrics (Plan §5.2), all from `daily_usage` (never raw events):

- [x] **topCustomers(Team $merchant, ?CarbonInterface $month = null): Collection** — current-month totals per customer, desc, limit 5. Month default = current UTC month. — Implemented over `DB::table` (join+groupBy+selectRaw) with explicit casts at the boundary; Eloquent dynamic attributes were unanalyzable by PHPStan.
- [x] **projectedOverageRevenue(Team $merchant): array** — linear projection `projected_usage = usage_to_date / days_elapsed × cycle_days` (half-up), day-one guard `max(1, days_elapsed)`; returns per-subscription rows + total cents.
- [x] **churnRiskCustomers(Team $merchant): Collection** — >50% MoM drop (threshold from config); previous-month = 0 excluded by convention; before/after figures in payload.
- [x] All queries merchant-scoped; qualified `daily_usage.` columns after the customers join (SQLite raised `ambiguous column: merchant_id`).

### 2.3 Endpoint — `app/Http/Controllers/Api/DashboardController.php`

```text
GET /api/merchants/{id}/dashboard   [ResolveApiKey OR team session]  → D4.2
```

- [x] Auth: `X-Api-Key` resolves the merchant (`{id}` must match, else 403). Fallback: session-authenticated user via `belongsToTeam()` (403 for non-members, 401 anonymous). Route runs `ResolveApiKey:optional` + cookie/session middleware because the default `api` group has none — key-only requests stay valid since auth is enforced in the controller, not by `auth` middleware.
- [x] Response (plain typed array):

```json
{
  "data": {
    "merchant": {"id": 1, "name": "…"},
    "month": "2026-09",
    "top_customers": [{"customer_id": 1, "name": "…", "total_quantity": 123456}, …],
    "projected_overage": {"total_cents": 12345, "subscriptions": [{"subscription_id": 9, "customer_name": "…", "usage_to_date": 8000, "projected_usage": 24000, "included_units": 10000, "projected_overage_units": 14000, "projected_overage_cents": 3500}]},
    "churn_risk": [{"customer_id": 3, "name": "…", "previous_month": 90000, "current_month": 12000, "drop_percentage": 86.7}]
  }
}
```

### 2.4 Tests — `tests/Feature/Dashboard/DashboardApiTest.php`

- [x] top-5 ordering + limit (6 customers with distinct usage → only top 5, correct order)
- [x] projection math: known usage/days → exact projected overage units/cents (hand-computed fixtures: 200→6,000 day-one; 800→2,400 over 10 elapsed days)
- [x] `days_elapsed = 1` (day-one guard)
- [x] churn: >50% drop flagged; ≤50% not; **previous month zero → excluded**
- [x] current month zero + previous >0 → 100% drop → flagged
- [x] tenant isolation: another merchant's data never appears; mismatched `{id}` → 403
- [x] **cache invalidation test**: plan `base_price` mutation → next `PlanPricingCache::get` returns fresh value
- [x] cache hit path: second `get` within TTL issues zero queries (query-log proof)
- [x] session team-member access without a key; outsider → 403; anonymous → 401

## 3. Acceptance Criteria

- [x] Dashboard payload contains all three metrics with correct math on hand-computed fixtures.
- [x] Plan mutation → pricing cache invalidated (test-proven); billing reads via cache.
- [x] Tenant isolation on the dashboard endpoint.
- [x] Pint + PHPStan clean; tests green. — 10/10 dashboard tests; full suite 157/157.

## 4. Notes & Links

- `shouldRenderJsonWhen` already covers `api/*` — no extra content negotiation.
- Month boundaries are UTC; keep `Carbon::create($year, $month)->startOfMonth()` style and don't mix local tz.
- Projection is an estimate for the dashboard only — never stored, never billed.
