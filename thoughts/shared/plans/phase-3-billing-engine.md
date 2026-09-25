# Phase 3 — Billing Engine

> Prereqs: Phase 2 complete. · Follows: Plan §4, §11 Phase 3 · Unlocks: Phase 4/5
> Goal: day-based proration, per-segment overage, mid-cycle plan changes (close/open segments), invoice + items generation in one transaction. Plus the supporting CRUD endpoints (plans, customers, subscriptions, plan change, invoice).

---

## 1. Scope

**In scope**

- `invoices` + `invoice_items` migrations
- `ProrationService` (pure math, unit-testable)
- `BillingService` (segments → items → invoice)
- `ChangeSubscriptionPlanAction` (segment close/open, zero-day-segment guard)
- `CreateSubscriptionAction` (initial segment)
- `GenerateInvoiceJob` + `GenerateInvoiceAction` + `POST /api/subscriptions/{id}/invoice`
- Supporting CRUD: `GET/POST /api/plans`, `GET /api/customers`, `POST /api/subscriptions`, `PATCH /api/subscriptions/{id}/plan`
- Full Plan §10 billing test matrix

**Out of scope**

- Plan pricing cache (Phase 4 — billing reads plans directly this phase) · dashboard metrics (Phase 4) · UI (Phase 5)

---

## 2. Billing Model (locked from Plan §4 + shared conventions)

Half-open `[start, end)` date intervals, UTC, day-based proration (C0.4). Money in integer cents, overage rate in basis-point-compatible integer math, **half-up rounding per invoice line** (C0.3/D3.1).

### 2.1 Formulas (integer domain)

```text
active_days      = ends_at->diffInDays(starts_at)          // segment length in days
prorated_base    = round_half_up(base_price_cents × active_days / cycle_days)
prorated_included= round_half_up(included_units × active_days / cycle_days)   // D3.3
segment_usage    = Σ daily_usage.total_quantity WHERE usage_date ∈ [seg_start, seg_end)
overage_units    = max(0, segment_usage − prorated_included)
overage_cents    = round_half_up(overage_units × overage_rate_micros)         // rate stored 4dp; scale to micro-units per unit for int math
line_total       = prorated_base + overage_cents
invoice_total    = Σ line_totals
```

### 2.2 Segment resolution

- Segments live in `subscription_segments`; **the only authority** for "which plan covers which day" (Plan §3.2).
- To bill a cycle `[P_start, P_end)`: take segments overlapping the period, **clip** them to the period, skip zero-length clips (D3.4 boundary rule: change effective on cycle boundary ⇒ single full segment).
- Segment with zero usage still bills prorated base (D3.4).

---

## 3. Tasks

### 3.1 Migrations

- [ ] **`invoices`** (Plan §3.2): `id`, `subscription_id` FK, `merchant_id` FK, `customer_id` FK (denormalized), `period_start date`, `period_end date`, `total_amount decimal(12,2)`, `status` (enum values draft/finalized/paid), timestamps. Indexes `(subscription_id, period_start)`; **unique `(subscription_id, period_start)`** (D3.5 idempotent invoicing).
- [ ] **`invoice_items`** (Plan §3.2): `id`, `invoice_id` FK, `plan_id` FK, `segment_start`, `segment_end`, `prorated_base decimal(12,2)`, `included_units unsignedBigInteger`, `billable_usage unsignedBigInteger`, `overage_units unsignedBigInteger`, `overage_amount decimal(12,2)`, `line_total decimal(12,2)`. Index `(invoice_id)`.

### 3.2 `ProrationService` (`app/Services/ProrationService.php`) — pure functions

- [ ] `proratedBaseCents(int $baseCents, int $activeDays, int $cycleDays): int`
- [ ] `proratedIncludedUnits(int $includedUnits, int $activeDays, int $cycleDays): int`
- [ ] `overageCents(int $overageUnits, int $rateMicros): int` // rate 4dp → micro-units: rate × 10000 = micros per unit
- [ ] All integer math; half-up rounding helper (`intdiv(n + d/2, d)` pattern — write once, test it).

### 3.3 `BillingService` (`app/Services/BillingService.php`)

- [ ] `segmentsForPeriod(Subscription $sub, CarbonInterface $start, CarbonInterface $end): Collection` — clip + filter zero-length.
      -For usage: `segmentUsage(customerId, segStart, segEnd): int` — **sum from `daily_usage` only** (Plan §6: "reads daily_usage only, never raw events").
- [ ] `buildInvoice(Subscription $sub, CarbonInterface $periodStart, CarbonInterface $periodEnd): Invoice` — resolve segments → per-segment item rows → invoice in **one `DB::transaction`**.
- [ ] Idempotency (D3.5): first-or-existing on `(subscription_id, period_start)`; regenerate only when status = draft (delete draft items, rebuild). Finalized/paid invoices are never rebuilt.
- [ ] Invoice status starts `draft`; separate `finalize()` transition (keep simple: generation creates draft; endpoint may finalize — decide in implementation, keep consistent in tests).

### 3.4 Plan change — `ChangeSubscriptionPlanAction`

- [ ] Closes the current segment at (effective date − 1 day) and opens a new segment from the effective date, ending at cycle end (Plan §3.2 subscription_segments note).
- [ ] Boundary rule: effective **on** cycle boundary ⇒ **no zero-day segment** — just extend/adjust so a single full segment results.
- [ ] Update denormalized `subscriptions.plan_id` + `ends_at` if needed.
- [ ] Validate: effective date within the current cycle; plan belongs to the same merchant; plan active.

### 3.5 `CreateSubscriptionAction`

- [ ] Creates subscription + initial segment `[starts_at, cycle_end)`; sets denormalized `merchant_id`, `plan_id`, `status=active`.
- [ ] Initial cycle end = `starts_at + billing_cycle_days` of the plan.

### 3.6 Job + endpoint

- [ ] `GenerateInvoiceJob` (queue `billing`): wraps `GenerateInvoiceAction` → `BillingService::buildInvoice`. `$tries=3`, backoff.
- [ ] `POST /api/subscriptions/{id}/invoice` (body: `period_start`, `period_end` optional — default current cycle) → validates ownership via API-key merchant → dispatches job (sync in tests) → returns invoice payload (Eloquent API resource) when run synchronously, or `202` + job accepted when queued. **Decide and be consistent:** for testability, have the endpoint call the action directly and dispatch the job for the scheduled/batch path; document.

### 3.7 Supporting CRUD (API key + merchant scoped)

- [ ] `GET /api/plans` (list merchant's, active only by default) · `POST /api/plans` (validated; on create → **Phase 4 will add cache invalidation — no-op this phase**).
- [ ] `GET /api/customers` (list merchant's).
- [ ] `POST /api/subscriptions` (customer + plan, starts_at default today) → `CreateSubscriptionAction`.
- [ ] `PATCH /api/subscriptions/{id}/plan` (plan_id, effective_date default today) → `ChangeSubscriptionPlanAction`.

### 3.8 Tests

**`tests/Unit/ProrationServiceTest.php`** — pure math:

- [ ] full cycle → no proration
- [ ] half cycle → half base / half allowance (clean numbers)
- [ ] rounding: e.g. base 999 cents × 7/30 → exact half-up value
- [ ] allowance rounding half-up to whole units
- [ ] overage rate micros math

**`tests/Feature/Billing/BillingTest.php`** — Plan §10 "Billing" matrix:

- [ ] usage within allowance → base only
- [ ] exactly at allowance → zero overage
- [ ] above allowance → correct overage units/amount
- [ ] zero usage → base only (D3.4)
- [ ] mid-cycle subscription start → prorated base
- [ ] multiple segments → per-segment totals
- [ ] upgrade mid-cycle → old usage at old rate, new usage at new rate
- [ ] downgrade mid-cycle → same, prorated allowances
- [ ] invoice total = Σ segments
- [ ] idempotent invoice generation (same subscription+period twice → one invoice)

**`tests/Feature/Api/SubscriptionPlanChangeTest.php`**:

- [ ] PATCH plan change closes/opens segments correctly (dates, no zero-day segments at boundaries)
- [ ] tenant isolation on all new endpoints

## 4. Acceptance Criteria

- [ ] All Plan §10 billing rows green; boundary dates (cycle-boundary change) produce a single segment.
- [ ] Invoices + items written atomically; unique constraint prevents duplicate invoices.
- [ ] Old usage billed at old plan's rate after mid-cycle change; new usage at new plan's rate.
- [ ] Pint + PHPStan clean.

## 5. Notes & Links

- The rate scaling: `overage_rate decimal(10,4)` → for integer math multiply by 10,000 (micros). Keep a single helper; never scatter scaling constants.
- `BillingService` must not read `usage_events` — enforce in review/tests by data setup (daily_usage seeded independently of events).
- Money as decimal columns; conversion to cents at service boundary (`(int) round($plan->base_price * 100)` once per plan read).
