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

- [x] **`invoices`** (Plan §3.2): `id`, `subscription_id` FK, `merchant_id` FK, `customer_id` FK (denormalized), `period_start date`, `period_end date`, `total_amount decimal(12,2)`, `status` (enum values draft/finalized/paid), timestamps. Indexes `(subscription_id, period_start)`; **unique `(subscription_id, period_start)`** (D3.5 idempotent invoicing).
- [x] **`invoice_items`** (Plan §3.2): `id`, `invoice_id` FK, `plan_id` FK, `segment_start`, `segment_end`, `prorated_base decimal(12,2)`, `included_units unsignedBigInteger`, `billable_usage unsignedBigInteger`, `overage_units unsignedBigInteger`, `overage_amount decimal(12,2)`, `line_total decimal(12,2)`. Index `(invoice_id)`.

### 3.2 `ProrationService` (`app/Services/ProrationService.php`) — pure functions

- [x] `proratedBaseCents(int $baseCents, int $activeDays, int $cycleDays): int`
- [x] `proratedIncludedUnits(int $includedUnits, int $activeDays, int $cycleDays): int`
- [x] `overageCents(int $overageUnits, int $rateMicros): int` — **correction discovered in tests**: micros are micro-**dollars**, so `cents = units × micros / 100` (not `/10_000`). Unit tests caught the 100× error.
- [x] All integer math; half-up rounding helper (`intdiv` + remainder≥half → +1 pattern).

### 3.3 `BillingService` (`app/Services/BillingService.php`)

- [x] `segmentsForPeriod(Subscription $sub, CarbonInterface $start, CarbonInterface $end): Collection` — clip + filter zero-length. — Note: type hints use `Carbon\CarbonInterface` because the app globally uses `CarbonImmutable` (`Date::use()`), so model date casts return immutable instances.
      -For usage: `segmentUsage(customerId, segStart, segEnd): int` — **sum from `daily_usage` only** (Plan §6: "reads daily_usage only, never raw events").
- [x] `buildInvoice(Subscription $sub, CarbonInterface $periodStart, CarbonInterface $periodEnd): Invoice` — resolve segments → per-segment item rows → invoice in **one `DB::transaction`**.
- [x] Idempotency (D3.5): first-or-existing on `(subscription_id, period_start)`; regenerate only when status = draft (delete draft items, rebuild). Finalized/paid invoices are never rebuilt.
- [x] Invoice status starts `draft`; generation endpoint returns the draft (200); finalization is a model-level status transition exercised in tests.

### 3.4 Plan change — `ChangeSubscriptionPlanAction`

- [x] Closes the current segment and opens a new segment from the effective date, ending at cycle end. — **Correction found by tests**: with half-open intervals, the old segment's `ends_at` must equal the effective date (not effective − 1) or a one-day gap goes unbilled.
- [x] Boundary rule: effective **on** cycle boundary ⇒ **no zero-day segment** — just extend/adjust so a single full segment results.
- [x] Update denormalized `subscriptions.plan_id` + `ends_at` if needed.
- [x] Validate: effective date within the current cycle; plan belongs to the same merchant; plan active.

### 3.5 `CreateSubscriptionAction`

- [x] Creates subscription + initial segment `[starts_at, cycle_end)`; sets denormalized `merchant_id`, `plan_id`, `status=active`.
- [x] Initial cycle end = `starts_at + billing_cycle_days` of the plan.

### 3.6 Job + endpoint

- [x] `GenerateInvoiceJob` (queue `billing`): wraps `GenerateInvoiceAction` → `BillingService::buildInvoice`. `$tries=3`, backoff.
- [x] `POST /api/subscriptions/{id}/invoice` — **decision**: endpoint calls the action synchronously and returns the invoice (200, idempotent semantics); the queued job serves the scheduled/batch path.

### 3.7 Supporting CRUD (API key + merchant scoped)

- [x] `GET /api/plans` (list merchant's, active only by default; `?include_inactive=1` for all) · `POST /api/plans` (validated; Phase 4 adds cache invalidation).
- [x] `GET /api/customers` (list merchant's).
- [x] `POST /api/subscriptions` (customer + plan, starts_at default today) → `CreateSubscriptionAction`.
- [x] `PATCH /api/subscriptions/{id}/plan` (plan_id, effective_date default today) → `ChangeSubscriptionPlanAction`.

### 3.8 Tests

**`tests/Unit/ProrationServiceTest.php`** — pure math:

- [x] full cycle → no proration
- [x] half cycle → half base / half allowance (clean numbers)
- [x] rounding: e.g. base 999 cents × 7/30 → exact half-up value
- [x] allowance rounding half-up to whole units
- [x] overage rate micros math

**`tests/Feature/Billing/BillingTest.php`** — Plan §10 "Billing" matrix:

- [x] usage within allowance → base only
- [x] exactly at allowance → zero overage
- [x] above allowance → correct overage units/amount
- [x] zero usage → base only (D3.4)
- [x] mid-cycle subscription start → prorated base
- [x] multiple segments → per-segment totals
- [x] upgrade mid-cycle → old usage at old rate, new usage at new rate
- [x] downgrade mid-cycle → same, prorated allowances
- [x] invoice total = Σ segments
- [x] idempotent invoice generation (same subscription+period twice → one invoice)
- [x] finalized invoices are never rebuilt (extra regression test)

**`tests/Feature/Api/SubscriptionPlanChangeTest.php`**:

- [x] PATCH plan change closes/opens segments correctly (dates, no zero-day segments at boundaries)
- [x] tenant isolation on all new endpoints

## 4. Acceptance Criteria

- [x] All Plan §10 billing rows green; boundary dates (cycle-boundary change) produce a single segment.
- [x] Invoices + items written atomically; unique constraint prevents duplicate invoices.
- [x] Old usage billed at old plan's rate after mid-cycle change; new usage at new plan's rate.
- [x] Pint + PHPStan clean. — 24/24 Phase 3 tests; full suite 147/147 (521 assertions).

## 5. Notes & Links

- The rate scaling: `overage_rate decimal(10,4)` → for integer math multiply by 10,000 (micros). Keep a single helper; never scatter scaling constants.
- `BillingService` must not read `usage_events` — enforce in review/tests by data setup (daily_usage seeded independently of events).
- Money as decimal columns; conversion to cents at service boundary (`(int) round($plan->base_price * 100)` once per plan read).
