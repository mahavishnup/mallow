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

- [ ] Key: `plan:{id}:pricing` → `{base_price_cents, included_units, overage_rate_micros, cycle_days}` (Plan §7).
- [ ] `get(Plan $plan): PricingDTO` — read-through: cache hit → DTO; miss → build from model, `Cache::put` with `config('billing.cache.plan_pricing_ttl')`.
- [ ] `forget(int $planId): void` → `Cache::forget`.
- [ ] **Write-through invalidation**: hook into every plan mutation path — Plan controller update/create + any seeder/command that mutates. Implementation: model hook (`static::saved`/`updated` on `Plan`) is the most leak-proof (works for Eloquent saves regardless of caller). Use `Cache::forget` on saved+deleted events, only when pricing-relevant attributes are dirty (`base_price`, `included_units`, `overage_rate`, `billing_cycle_days`).
- [ ] Billing read path: Phase 3's `BillingService` switches its direct `Plan` pricing reads to `PlanPricingCache` (single read per plan per run — Plan §7 billing safety). Keep `BillingService` tests passing; add one cache-hit assertion.

### 2.2 `DashboardService` (`app/Services/DashboardService.php`)

Metrics (Plan §5.2), all from `daily_usage` (never raw events):

- [ ] **topCustomers(Team $merchant, ?CarbonInterface $month = null): Collection** — current-month totals per customer, desc, limit 5. Month default = current UTC month.
- [ ] **projectedOverageRevenue(Team $merchant): array|Collection** — for each active subscription: `usage_to_date` (cycle-to-date from daily_usage) vs `prorated_included_so_far`; current overage; **linear projection** to cycle end: `projected_usage = usage_to_date / days_elapsed × cycle_days` (documented assumption, `config('billing.dashboard.projection')`). Return per-subscription rows + total projected overage revenue. Guard `days_elapsed = 0` (day 1 of cycle → projection = usage_to_date × cycle_days? decide: use max(1, days_elapsed)).
- [ ] **churnRiskCustomers(Team $merchant): Collection** — customers whose current-month usage < previous-month usage × (1 − 0.5), i.e. **>50% MoM drop** (threshold from config). **Exclude previous-month = 0** (documented convention — no divide-by-zero, no false churn flags for new customers). Include before/after figures in payload.
- [ ] All queries merchant-scoped; use the `(merchant_id, usage_date)` indexes.

### 2.3 Endpoint — `app/Http/Controllers/Api/DashboardController.php`

```text
GET /api/merchants/{id}/dashboard   [ResolveApiKey OR team session]  → D4.2
```

- [ ] Auth: if `X-Api-Key` resolved a merchant → use it (and `{id}` must match, else 403). Else fall back to authenticated web user with membership on team `{id}` (reuse `EnsureTeamMembership` logic or a lightweight check). This lets the React dashboard (Phase 5) hit the same JSON when needed and keeps the machine API honest.
- [ ] Response (API Resource or plain typed array):

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

- [ ] top-5 ordering + limit (6 customers with distinct usage → only top 5, correct order)
- [ ] projection math: known usage/days → exact projected overage units/cents (hand-computed fixture)
- [ ] `days_elapsed = 1` (day-one guard)
- [ ] churn: >50% drop flagged; ≤50% not; **previous month zero → excluded**
- [ ] current month zero + previous >0 → 100% drop → flagged
- [ ] tenant isolation: another merchant's data never appears
- [ ] **cache invalidation test**: mutate a plan's `base_price` via API → next `PlanPricingCache::get` returns fresh value (not stale)
- [ ] cache hit path: second `get` within TTL doesn't query DB (assert query count or use `Cache::spy`)

## 3. Acceptance Criteria

- [ ] Dashboard payload contains all three metrics with correct math on hand-computed fixtures.
- [ ] Plan mutation → pricing cache invalidated (test-proven); billing reads via cache.
- [ ] Tenant isolation on the dashboard endpoint.
- [ ] Pint + PHPStan clean; tests green.

## 4. Notes & Links

- `shouldRenderJsonWhen` already covers `api/*` — no extra content negotiation.
- Month boundaries are UTC; keep `Carbon::create($year, $month)->startOfMonth()` style and don't mix local tz.
- Projection is an estimate for the dashboard only — never stored, never billed.
