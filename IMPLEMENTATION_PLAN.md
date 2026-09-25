# Implementation Plan — Subscription Billing & Usage-Metering Platform

**Project:** Subscription Billing & Usage-Metering System
**Stack:** Laravel 13 (PHP 8.4) · React (Inertia) Starter Kit · PostgreSQL · Redis · Queues

---

## 1. Project Overview

A multi-tenant platform where merchants subscribe customers to plans, record usage events over a high-volume API, aggregate those events asynchronously, and generate invoices with correct proration and overage billing — including mid-cycle plan upgrades/downgrades. A dashboard surfaces usage insights per merchant.

### Core Capabilities

| #   | Capability                                                             | Priority |
| --- | ---------------------------------------------------------------------- | -------- |
| 1   | Normalized, indexed schema supporting 5M+ usage events                 | P0       |
| 2   | Idempotent `POST /usage` ingestion API with rate limiting              | P0       |
| 3   | Queued, chunked, retry-safe daily aggregation                          | P0       |
| 4   | Billing engine: base price, proration, overage, segmented plan changes | P0       |
| 5   | Plan/pricing cache with explicit invalidation                          | P1       |
| 6   | Dashboard API: top-5 usage, projected overage, >50% MoM drop           | P0       |
| 7   | React dashboard UI                                                     | P0       |
| 8   | Automated tests (aggregation + billing edge cases)                     | P0       |
| 9   | Seed/demo data + README                                                | P1       |

---

## 2. System Architecture

```text
React Dashboard (Inertia)
        |
        v
Laravel API  (thin controllers)
        |
        +--> Form Requests .............. validation
        |
        +--> Actions/Services ........... all business logic
        |       +-- RecordUsageAction
        |       +-- AggregateDailyUsageAction
        |       +-- BillingService
        |       +-- ProrationService
        |       +-- DashboardService
        |       +-- PlanPricingCache
        |
        +--> Jobs (queue)
        |       +-- AggregateDailyUsageJob
        |       +-- GenerateInvoiceJob
        |
        +--> Models .................... Eloquent + strict mapper
        |
        +--> PostgreSQL ................ normalized OLTP store
        |
        +--> Redis ..................... cache + queue driver
```

**Principles**

- Controllers stay thin: validate → delegate to action/service → return JSON/resource.
- Raw `usage_events` are the immutable source of truth; `daily_usage` is a derived, read-optimized aggregate that can always be rebuilt from events.
- All aggregation and billing run off `daily_usage`, never by scanning raw events.
- Every write path is idempotent at the **database constraint level**, not just application checks.

---

## 3. Database Schema

### 3.1 Entity Relationship

```text
Merchant (tenant)
  |-- Plans
  |-- Customers
  |      |-- Subscriptions
  |      |      |-- SubscriptionSegments (plan history)
  |      |      |-- Invoices
  |      |             |-- InvoiceItems (billing segments)
  |      |-- UsageEvents
  |      |-- DailyUsage
```

### 3.2 Tables & Columns

#### `plans`

| Column             | Type                 | Notes                      |
| ------------------ | -------------------- | -------------------------- |
| id                 | bigIncrements        |                            |
| merchant_id        | foreignId            | plans are per-merchant     |
| name               | string               |                            |
| base_price         | decimal(10,2)        | monthly base               |
| included_units     | unsignedBigInteger   | included monthly allowance |
| overage_rate       | decimal(10,4)        | per-unit overage price     |
| billing_cycle_days | unsignedSmallInteger | default 30                 |
| is_active          | boolean              | soft-disable plans         |

#### `customers`

| Column       | Type          | Notes               |
| ------------ | ------------- | ------------------- |
| id           | bigIncrements |                     |
| merchant_id  | foreignId     | tenant scoping      |
| name / email | string        | unique per merchant |

#### `subscriptions`

| Column              | Type          | Notes                                          |
| ------------------- | ------------- | ---------------------------------------------- |
| id                  | bigIncrements |                                                |
| customer_id         | foreignId     |                                                |
| plan_id             | foreignId     | **current** plan (denormalized for fast reads) |
| starts_at / ends_at | date          | billing cycle boundaries (current cycle)       |
| status              | enum          | active, canceled                               |

#### `subscription_segments` (plan-period history)

| Column              | Type          | Notes                            |
| ------------------- | ------------- | -------------------------------- |
| id                  | bigIncrements |                                  |
| subscription_id     | foreignId     |                                  |
| plan_id             | foreignId     | plan in force during this period |
| starts_at / ends_at | date          | half-open interval [start, end)  |
| timestamps          |               | audit                            |

One row per contiguous plan period. A mid-cycle change closes the old segment the day before the change and opens a new segment. This is the **only** authority for "which plan covers which day."

#### `usage_events` (high volume, immutable)

| Column                 | Type               | Notes                               |
| ---------------------- | ------------------ | ----------------------------------- |
| id                     | bigIncrements      |                                     |
| uuid / idempotency_key | uuid               | **unique** — final retry protection |
| merchant_id            | foreignId          |                                     |
| customer_id            | foreignId          |                                     |
| usage_date             | date               |                                     |
| quantity               | unsignedBigInteger |                                     |
| type                   | string             | e.g. api_calls, emails              |
| aggregated_at          | timestamp nullable | aggregation watermark               |
| timestamps             |                    |                                     |

#### `daily_usage` (derived aggregate)

| Column         | Type               | Notes |
| -------------- | ------------------ | ----- |
| id             | bigIncrements      |       |
| merchant_id    | foreignId          |       |
| customer_id    | foreignId          |       |
| usage_date     | date               |       |
| total_quantity | unsignedBigInteger |       |
| event_count    | unsignedInteger    |       |
| timestamps     |                    |       |

**Unique:** `(customer_id, usage_date)` — enables idempotent `upsert`.

#### `invoices`

| Column                    | Type          | Notes                   |
| ------------------------- | ------------- | ----------------------- |
| id                        | bigIncrements |                         |
| subscription_id           | foreignId     |                         |
| merchant_id / customer_id | foreignId     | denormalized for lookup |
| period_start / period_end | date          |                         |
| total_amount              | decimal(12,2) |                         |
| status                    | enum          | draft, finalized, paid  |

#### `invoice_items` (billing segments)

| Column                      | Type               | Notes                          |
| --------------------------- | ------------------ | ------------------------------ |
| id                          | bigIncrements      |                                |
| invoice_id                  | foreignId          |                                |
| plan_id                     | foreignId          | plan for this segment          |
| segment_start / segment_end | date               |                                |
| prorated_base               | decimal(12,2)      |                                |
| included_units              | unsignedBigInteger | prorated allowance             |
| billable_usage              | unsignedBigInteger | segment usage                  |
| overage_units               | unsignedBigInteger |                                |
| overage_amount              | decimal(12,2)      |                                |
| line_total                  | decimal(12,2)      | prorated_base + overage_amount |

### 3.3 Index Strategy

| Index                                                                  | Purpose                                        |
| ---------------------------------------------------------------------- | ---------------------------------------------- |
| `usage_events(idempotency_key) unique`                                 | DB-level idempotency, fast duplicate detection |
| `usage_events(customer_id, usage_date)`                                | per-customer/day aggregation lookups           |
| `usage_events(merchant_id, usage_date)`                                | merchant-wide scans, dashboard                 |
| `usage_events(aggregated_at)` partial `WHERE aggregated_at IS NULL`    | aggregation job picks up only unprocessed rows |
| `daily_usage(customer_id, usage_date) unique`                          | upsert target, billing reads                   |
| `daily_usage(merchant_id, usage_date)`                                 | dashboard month rollups                        |
| `subscriptions(customer_id)`, `subscriptions(merchant_id, status)`     | lookups                                        |
| `subscription_segments(subscription_id, starts_at)`                    | segment resolution                             |
| `invoices(subscription_id, period_start)`, `invoice_items(invoice_id)` | billing reads                                  |

### 3.4 Scale Notes (documented in README)

- Raw events stay normalized and immutable; aggregates avoid repeated scans of 5M+ rows.
- Composite indexes ordered by selectivity and actual query patterns.
- Future: monthly range partitioning of `usage_events` on `usage_date`; retention/archival of events beyond N months (daily_usage remains); read replica for reporting; bulk inserts for ingestion bursts.

---

## 4. Billing Model (define before coding)

### 4.1 Conventions (assumptions locked in)

- Billing cycles are date-based, half-open intervals `[period_start, period_end)` in **UTC**.
- Day-based proration: `active_days / billing_cycle_days`.
- Included units prorate **with the base price**, same fraction.
- Overage is computed **per segment** from that segment's usage and prorated allowance.
- Usage is attributed to a segment by the event's `usage_date` — old usage is never repriced.

### 4.2 Formulas

```text
prorated_base        = plan.base_price × active_days / cycle_days
prorated_included    = plan.included_units × active_days / cycle_days
segment_usage        = Σ daily_usage for dates within segment
overage_units        = max(0, segment_usage − prorated_included)
overage_amount       = overage_units × plan.overage_rate
segment_total        = prorated_base + overage_amount
invoice_total        = Σ segment_totals
```

### 4.3 Mid-Cycle Plan Change — Segment Example

```text
Cycle: Sep 1 → Sep 30 (30 days)

Segment 1: Sep 1–15   (15 days) Old plan, old allowance/rate, usage Sep 1–15
Segment 2: Sep 16–30  (15 days) New plan, new allowance/rate, usage Sep 16–30

invoice_total = seg1.prorated_base + seg1.overage
              + seg2.prorated_base + seg2.overage
```

Upgrades and downgrades follow the same segment model — the only difference is which plan each segment references. A change effective **on** a cycle boundary produces a single full segment (no degenerate zero-day segments).

---

## 5. API Design

### 5.1 `POST /api/usage`

**Request**

```json
{
    "customer_id": 12,
    "usage_date": "2026-09-20",
    "quantity": 250,
    "type": "api_calls",
    "idempotency_key": "0d0f4c2e-…"
}
```

**Flow**

```text
request
  → rate limiter (merchant-scoped)          → 429 on exceed
  → FormRequest validation                  → 422 on failure
  → tenant/ownership check (customer belongs to merchant)
  → RecordUsageAction
       → insert usage_event (unique idempotency_key enforced by DB)
       → on unique violation → return 200 { duplicate: true }  (no double count)
       → dispatch AggregateDailyUsageJob (afterCommit)
  → 201 response
```

**Decisions**

- Uniqueness enforced by DB constraint; app-level `firstOrNew` only as a fast path — never the only guard.
- No synchronous aggregation in the request path.
- Small transaction scope (single insert).

### 5.2 `GET /api/merchants/{id}/dashboard`

Returns:

1. **Top 5 customers by current-month usage** — from `daily_usage`, ordered desc, limit 5.
2. **Projected overage revenue** — for each active subscription: current usage vs included allowance → current overage → overage revenue; project to cycle end via documented assumption (linear: `projected = usage_to_date / days_elapsed × cycle_days`).
3. **Churn-risk customers** — current vs previous month usage; drop > 50%. Convention: previous month = 0 → excluded (no divide-by-zero, documented).

### 5.3 Supporting Endpoints

| Method   | Path                              | Purpose                                       |
| -------- | --------------------------------- | --------------------------------------------- |
| GET/POST | `/api/plans`                      | plan management (triggers cache invalidation) |
| GET      | `/api/customers`                  | customer list                                 |
| POST     | `/api/subscriptions`              | create subscription                           |
| PATCH    | `/api/subscriptions/{id}/plan`    | mid-cycle change → closes/opens segments      |
| POST     | `/api/subscriptions/{id}/invoice` | generate invoice for cycle                    |

---

## 6. Queue & Aggregation

### `AggregateDailyUsageJob` / `AggregateDailyUsageAction`

```text
pick unprocessed events (aggregated_at IS NULL), ordered by id, chunkById(1000)
  for each chunk:
    group by (customer_id, usage_date)
    upsert daily_usage (unique customer_id+usage_date):
        total_quantity = daily_usage.total_quantity + delta
        event_count    = daily_usage.event_count + count
    mark events aggregated_at = now()   (same transaction)
```

**Properties**

- **Idempotent / retry-safe:** watermark (`aggregated_at`) advances in the same transaction as the upsert. A crashed job re-runs only unmarked rows.
- **Chunked:** `chunkById` with a primary-key cursor — no offset scans, bounded memory.
- **Duplicate dispatch:** if two jobs pick the same rows, row-level locking on upsert (`update ... where`) keeps totals correct; events marked once.
- Local: `php artisan queue:work --queue=default` (Redis driver). Production story (Supervisor / Horizon) documented in README.

### `GenerateInvoiceJob`

Reads `daily_usage` only (never raw events), resolves segments, applies §4 formulas, writes invoice + items in one transaction.

---

## 7. Caching

| Concern        | Decision                                                                                                                                   |
| -------------- | ------------------------------------------------------------------------------------------------------------------------------------------ |
| Store          | Redis primary; array/file fallback documented                                                                                              |
| Key            | `plan:{id}:pricing` → `{base_price, included_units, overage_rate, cycle_days}`                                                             |
| TTL            | 24h safety net + **explicit invalidation** on any plan change                                                                              |
| Invalidation   | plan saved / price, included units, overage rate, cycle changed → `Cache::forget("plan:{id}:pricing")`                                     |
| Billing safety | invoice generation reads pricing once per plan per run; stale-cache window eliminated by write-through invalidation on every plan mutation |

---

## 8. Rate Limiting

- `RateLimiter::for('usage', …)` scoped per **merchant (API key)** with configurable buckets (e.g. 600/min merchant-wide, 60/min per customer).
- Exceed → HTTP 429 + `Retry-After`.
- Limits read from config (`config/billing.php`) — tunable per environment; rationale documented.

---

## 9. React Dashboard

Pages under `resources/js/pages`:

- Merchant context (current tenant) header with cycle info.
- **Top 5 customers** table (usage this month).
- **Projected overage revenue** card.
- **Churn risk** table (>50% MoM drop, with before/after figures).
- Loading, empty, and API-error states for each section.

UI polish is secondary to data correctness; keep to starter-kit patterns (Wayfinder routes, typed props).

---

## 10. Test Plan (Pest)

### Aggregation

- Single event → correct daily total
- Multiple events, same customer/day → summed
- Multiple customers → isolated totals
- Chunk boundary (events spanning chunk size) → correct totals
- Re-running aggregation → no double counting
- Duplicate `POST /usage` (same idempotency key) → no double counting

### Billing

- Usage within allowance → base only
- Usage exactly at allowance → zero overage
- Usage above allowance → correct overage units/amount
- Zero usage → base only
- Mid-cycle subscription start → prorated base
- Multiple segments → per-segment totals
- Upgrade mid-cycle → old rate for old usage, new rate for new usage
- Downgrade mid-cycle → same, with prorated allowances
- Final invoice total = Σ segments

### API / Platform

- Idempotency-key retry returns success-without-duplicate
- Rate limit returns 429
- Tenant isolation (merchant A cannot record for merchant B's customer)
- Dashboard metrics (top-5 ordering, projection math, MoM drop edge cases incl. prev-month zero)

---

## 11. Implementation Phases & Task Breakdown

### Phase 0 — Foundations (0.5 d)

- [ ] Config: Redis cache/queue in `.env`; `config/billing.php` (rate limits, chunk size, projection assumptions)
- [ ] Base migrations: merchants/tenants (extend existing), plans, customers, subscriptions, subscription_segments
- [ ] Models + relationships + factories
- [ ] Pint/PHPStan clean baseline

### Phase 1 — Usage Ingestion (0.5 d)

- [ ] `usage_events` + `daily_usage` migrations with all indexes
- [ ] `RecordUsageAction` (idempotent insert, ownership check)
- [ ] `POST /api/usage` controller + form request
- [ ] Rate limiter + wiring
- [ ] Tests: idempotency, validation, 429, tenant isolation

### Phase 2 — Aggregation (0.5 d)

- [ ] `AggregateDailyUsageJob` + action (chunked, watermarked, upsert)
- [ ] Queue config + local worker instructions
- [ ] Tests: chunking, idempotent re-runs, multi-customer

### Phase 3 — Billing Engine (1 d)

- [ ] `ProrationService` (day-based fractions)
- [ ] `BillingService` (segments → items → invoice)
- [ ] Plan-change handler (close/open segments, no zero-day segments)
- [ ] `GenerateInvoiceJob` + endpoint
- [ ] Tests: full §10 billing matrix (incl. upgrade/downgrade, boundary dates)

### Phase 4 — Cache + Dashboard API (0.5 d)

- [ ] `PlanPricingCache` (get/put/forget on mutation)
- [ ] `DashboardService` + `GET /api/merchants/{id}/dashboard`
- [ ] Tests: metrics + edge cases (zero previous month, exactly-at-allowance)

### Phase 5 — Frontend (0.5 d)

- [ ] Dashboard page(s), API wiring, loading/empty/error states
- [ ] Merchant context + cycle display

### Phase 6 — Polish & Docs (0.5 d, overlaps)

- [ ] Demo seeder (merchant, plans, customers, subscriptions, backdated usage, a plan change, an invoice)
- [ ] README: overview, architecture diagram, setup (PostgreSQL/Redis/env), migrations/seeding, queue worker, API reference, schema & index rationale, 5M+ scaling discussion, idempotency, queue/chunking, cache strategy & invalidation, billing/proration formulas, upgrade/downgrade handling, rate limiting, testing instructions, assumptions, trade-offs, future improvements
- [ ] `php artisan test` green from clean DB; frontend build green

---

## 12. Definition of Done

1. Clean clone → configure PostgreSQL/Redis → migrate → seed works end to end.
2. Usage recorded via API; identical retry does not double count.
3. Queue worker aggregates events into `daily_usage`; re-runs are safe.
4. Invoice generation produces correct base, proration, and overage totals.
5. Mid-cycle plan change bills old usage at old rates and new usage at new rates.
6. Dashboard shows top-5 usage, projected overage revenue, and >50% MoM-drop customers.
7. Full test suite passes; README explains architecture, assumptions, and trade-offs.
