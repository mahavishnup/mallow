# Subscription Billing & Usage-Metering Platform

A multi-tenant platform where merchants subscribe customers to plans, record usage events over a high-volume idempotent API, aggregate those events asynchronously, and generate invoices with correct proration and overage billing — including mid-cycle plan upgrades/downgrades. A dashboard surfaces usage insights per merchant.

**Stack:** Laravel 13 (PHP 8.4) · React (Inertia v3) · PostgreSQL · Redis (optional) · Queues · Pest

---

## Table of Contents

1. [Architecture](#architecture)
2. [Setup](#setup)
3. [Demo Data](#demo-data)
4. [API Reference](#api-reference)
5. [Database Schema & Indexing](#database-schema--indexing)
6. [Scaling to 5M+ Events](#scaling-to-5m-events)
7. [Ingestion & Idempotency](#ingestion--idempotency)
8. [Aggregation Pipeline](#aggregation-pipeline)
9. [Billing Engine](#billing-engine)
10. [Caching Strategy](#caching-strategy)
11. [Rate Limiting](#rate-limiting)
12. [Dashboard Metrics](#dashboard-metrics)
13. [Testing](#testing)
14. [Assumptions & Trade-offs](#assumptions--trade-offs)
15. [Future Improvements](#future-improvements)

---

## Architecture

```text
React Dashboard (Inertia)
        |
        v
Laravel API  (thin controllers)
        |
        +--> Form Requests .............. validation
        |
        +--> Actions .................... single business operations
        |       +-- RecordUsageAction
        |       +-- CreateSubscriptionAction
        |       +-- ChangeSubscriptionPlanAction
        |       +-- GenerateInvoiceAction
        |       +-- AggregateDailyUsageAction
        |
        +--> Services ................... domain engines + cache
        |       +-- BillingService
        |       +-- ProrationService (pure integer math)
        |       +-- DashboardService
        |       +-- PlanPricingCache
        |
        +--> Jobs (queue)
        |       +-- AggregateDailyUsageJob
        |       +-- GenerateInvoiceJob
        |
        +--> Models .................... Eloquent (final, strict types)
        |
        +--> PostgreSQL ................ normalized OLTP store
        |
        +--> Redis ..................... cache + queue driver (optional;
                                         database/file fallbacks documented)
```

**Principles**

- Controllers stay thin: validate → delegate to action/service → return JSON/resource.
- Raw `usage_events` are the immutable source of truth; `daily_usage` is a derived, read-optimized aggregate that can always be rebuilt from events.
- All aggregation and billing read `daily_usage`, never raw events.
- Every write path is idempotent at the **database constraint level**, not just application checks.

---

## Setup

### Prerequisites

- PHP 8.4 with typical Laravel extensions
- PostgreSQL 14+
- Composer, Node.js (with pnpm/npm)
- Redis — **optional**: queue/cache fall back to `database`/`file` drivers

### Steps

```bash
# 1. Install dependencies
composer install
npm install

# 2. Environment
cp .env.example .env
php artisan key:generate

# Configure PostgreSQL:
#   DB_CONNECTION=pgsql
#   DB_HOST=127.0.0.1
#   DB_DATABASE=mallow
#   DB_USERNAME=...
#   DB_PASSWORD=...

# Queue + cache (pick one column each):
#   Redis:     QUEUE_CONNECTION=redis   CACHE_STORE=redis
#   No Redis:  QUEUE_CONNECTION=database  CACHE_STORE=file

# 3. Database + demo data
php artisan migrate --seed        # includes the demo dataset (see below)

# 4. Frontend
npm run build                     # or: npm run dev for hot reload

# 5. Queue worker (separate terminal — required for async aggregation)
php artisan queue:work
```

With [Laravel Herd](https://herd.laravel.com/) the site is served at `https://mallow.test`.

### Verification

```bash
php artisan test --compact    # full suite (159 tests)
vendor/bin/phpstan analyse    # static analysis (level 7)
vendor/bin/pint --dirty       # formatting
```

---

## Demo Data

The default seeder includes `BillingDemoSeeder` (also runnable standalone):

```bash
php artisan db:seed --class=BillingDemoSeeder --no-interaction
```

It creates:

- One merchant team **Acme Metering Co** — login `demo@example.com` / `password`
- Three plans (Starter $29 / Growth $99 / Scale $299)
- 8 customers: 4 stable, 1 mid-cycle **upgrade** (Starter→Growth), 1 mid-cycle **downgrade** (Scale→Starter), 1 high-usage overage customer, 1 churn-risk customer
- Backdated `daily_usage` across current + previous months
- One engine-generated invoice proving correct segmented billing

The seeder prints a **demo API key** once — store it to exercise the API below.

Dashboard: log in and open `/acme-metering/dashboard` (Top customers, projected overage revenue, churn risk).

---

## API Reference

All machine endpoints require an `X-Api-Key` header (sha-256 hashed lookup → merchant). Create keys with:

```bash
php artisan billing:api-key --merchant=1 --name="CI key"
```

### `POST /api/usage` — record a usage event (idempotent)

```json
{
    "customer_id": 12,
    "usage_date": "2026-09-20",
    "quantity": 250,
    "type": "api_calls",
    "idempotency_key": "0d0f4c2e-1111-4111-8111-000000000001"
}
```

| Outcome                                                     | Response                                                    |
| ----------------------------------------------------------- | ----------------------------------------------------------- |
| Stored                                                      | `201 {data: {id, customer_id, usage_date, quantity, type}}` |
| Duplicate `idempotency_key` (retry)                         | `200 {duplicate: true}` — **never counted twice**           |
| Missing/invalid key                                         | `401`                                                       |
| Validation failure (future date, qty ≤ 0, bad UUID, …)      | `422`                                                       |
| Customer not owned / no active subscription on `usage_date` | `404` / `422`                                               |
| Rate limit exceeded                                         | `429` + `Retry-After`                                       |

The event lands in `usage_events` un-aggregated; `AggregateDailyUsageJob` (dispatched `afterCommit`) folds it into `daily_usage` asynchronously.

### `GET /api/merchants/{id}/dashboard`

Dual auth: `X-Api-Key` **or** an authenticated team-member session. Returns:

```json
{
    "data": {
        "merchant": { "id": 1, "name": "Acme Metering Co" },
        "month": "2026-09",
        "top_customers": [
            { "customer_id": 1, "name": "…", "total_quantity": 123456 }
        ],
        "projected_overage": {
            "total_cents": 12345,
            "subscriptions": [
                {
                    "subscription_id": 9,
                    "customer_name": "…",
                    "usage_to_date": 8000,
                    "projected_usage": 24000,
                    "included_units": 10000,
                    "projected_overage_units": 14000,
                    "projected_overage_cents": 3500
                }
            ]
        },
        "churn_risk": [
            {
                "customer_id": 3,
                "name": "…",
                "previous_month": 90000,
                "current_month": 12000,
                "drop_percentage": 86.7
            }
        ]
    }
}
```

### Plans / Customers / Subscriptions

| Method | Path                              | Purpose                                                                                     |
| ------ | --------------------------------- | ------------------------------------------------------------------------------------------- |
| GET    | `/api/plans`                      | List merchant's plans (active only; `?include_inactive=1` for all)                          |
| POST   | `/api/plans`                      | Create plan (invalidates pricing cache)                                                     |
| GET    | `/api/customers`                  | List merchant's customers                                                                   |
| POST   | `/api/subscriptions`              | `{customer_id, plan_id, starts_at?}` — creates subscription + initial segment               |
| PATCH  | `/api/subscriptions/{id}/plan`    | `{plan_id, effective_date?}` — mid-cycle change (closes/opens segments)                     |
| POST   | `/api/subscriptions/{id}/invoice` | `{period_start?, period_end?}` — generate/rebuild draft invoice (defaults to current cycle) |

All responses are merchant-scoped; cross-tenant access returns `404`.

### Smoke test

```bash
KEY="mall_..."   # from the seeder or billing:api-key

# Record usage (note: usage_date must be today-or-past; customer must exist)
curl -s -X POST https://mallow.test/api/usage \
  -H "X-Api-Key: $KEY" -H "Content-Type: application/json" \
  -d '{"customer_id":1,"usage_date":"'"$(date +%F)"'","quantity":250,"type":"api_calls","idempotency_key":"'"$(uuidgen)"'"}'

# Retry the exact same body → {"duplicate":true}, daily_usage unchanged (worker running)
```

---

## Database Schema & Indexing

```text
Merchant = Team (existing multi-tenant foundation)
  |-- Plans (per-merchant pricing)
  |-- Customers
  |      |-- Subscriptions (denormalized plan_id + merchant_id for fast reads)
  |      |      |-- SubscriptionSegments (plan history; ONLY authority for
  |      |      |            "which plan covers which day")
  |      |      |-- Invoices ── InvoiceItems (per-segment billing lines)
  |      |-- UsageEvents (immutable, high-volume)
  |      |-- DailyUsage (derived aggregate)
```

### Index rationale

| Index                                                               | Purpose                                                                    |
| ------------------------------------------------------------------- | -------------------------------------------------------------------------- |
| `usage_events(uuid) UNIQUE`                                         | **DB-level idempotency** — final retry protection                          |
| `usage_events(customer_id, usage_date)`                             | per-customer/day aggregation lookups                                       |
| `usage_events(merchant_id, usage_date)`                             | merchant-wide scans                                                        |
| `usage_events(aggregated_at) WHERE aggregated_at IS NULL` (partial) | aggregation cursor touches only unprocessed rows — stays cheap at 5M+ rows |
| `daily_usage(customer_id, usage_date) UNIQUE`                       | atomic `INSERT … ON CONFLICT` upsert target                                |
| `daily_usage(merchant_id, usage_date)`                              | dashboard month rollups                                                    |
| `subscriptions(customer_id)`, `(merchant_id, status)`               | lookups + active-subscription scans                                        |
| `subscription_segments(subscription_id, starts_at)`                 | segment resolution for billing                                             |
| `invoices(subscription_id, period_start) UNIQUE`                    | idempotent invoice generation                                              |

---

## Scaling to 5M+ Events

The design choices that keep this performant at volume:

- **Normalization + immutability**: raw events are append-only; no updates contend at scale.
- **Aggregates avoid repeated scans**: billing and dashboards read `daily_usage` (one row per customer/day), never raw events.
- **Composite indexes ordered by selectivity** and actual query patterns.
- **PK-cursor chunking** (`chunkById`) — no offset scans, bounded memory.

Future scaling paths (documented, not implemented):

- Monthly **range partitioning** of `usage_events` on `usage_date`
- **Retention/archival**: drop events older than N months (daily_usage remains)
- **Read replica** for dashboard/reporting queries
- **Bulk insert** path for ingestion bursts

---

## Ingestion & Idempotency

Uniqueness is enforced by the DB constraint; the app-level `exists()` check is only a fast path:

1. Request → rate limiter (merchant + customer buckets) → FormRequest validation
2. Tenant/ownership check: customer must belong to the key's merchant
3. Coverage check: an **active subscription must cover `usage_date`**
4. Insert with unique `uuid` — a concurrent duplicate loses the race and is caught (`SQLSTATE 23505` on PostgreSQL) → `200 {duplicate: true}`
5. `AggregateDailyUsageJob` dispatched `afterCommit` (scoped to customer+date)

Identical retries are always safe: same key → no second row, no double count — proven by tests including a simulated race.

---

## Aggregation Pipeline

`AggregateDailyUsageAction` (invoked by the queued job or the daily 02:00 safety-net sweep):

```text
pick unprocessed events (aggregated_at IS NULL), ordered by id, chunkById(1000)
  for each chunk (inside one transaction):
    group by (customer_id, usage_date)
    INSERT … ON CONFLICT (customer_id, usage_date) DO UPDATE
        total_quantity = daily_usage.total_quantity + excluded.total_quantity
        event_count    = daily_usage.event_count + excluded.event_count
    mark events aggregated_at = now()  (WHERE aggregated_at IS NULL guard)
```

- **Retry-safe**: the watermark advances in the same transaction as the upsert; a crashed job re-runs only unmarked rows.
- **Duplicate-dispatch safe**: row-level atomic increments + the `WHERE NULL` marking guard mean two workers can never double-count.
- Re-running the action any number of times never changes totals (test-proven).

---

## Billing Engine

All money is computed in **integer cents** (overage rate in micro-dollars: 4 dp × 10,000) with **half-up rounding per invoice line** — no floats anywhere in the money path.

```text
active_days       = segment length in days (half-open [start, end))
prorated_base     = round_half_up(base_cents × active_days / cycle_days)
prorated_included = round_half_up(included_units × active_days / cycle_days)
segment_usage     = Σ daily_usage WHERE usage_date ∈ [seg_start, seg_end)
overage_units     = max(0, segment_usage − prorated_included)
overage_cents     = round_half_up(overage_units × rate_micros / 100)
line_total        = prorated_base + overage_cents
invoice_total     = Σ line_totals
```

### Mid-cycle plan changes (segments)

`subscription_segments` is the only authority for "which plan covers which day". A change effective Sep 16 on a Sep 1–30 cycle:

```text
Segment 1: Sep 1–16   (15 days) Old plan, old allowance/rate, usage Sep 1–15
Segment 2: Sep 16–30  (14 days) New plan, new allowance/rate, usage Sep 16–29
```

- Old usage is **never repriced** — attribution is by `usage_date`.
- A change effective exactly on a segment boundary **replaces** the open segment (no zero-day segments).
- Invoices are idempotent: unique `(subscription_id, period_start)`; drafts rebuild, finalized/paid never do.

---

## Caching Strategy

- **Store**: Redis when available; `file`/`database` fallbacks work identically (array in tests).
- **Key**: `plan:{id}:pricing` → integer-domain DTO (`base_price_cents`, `included_units`, `overage_rate_micros`, `cycle_days`).
- **TTL**: 24h safety net (`config/billing.php`).
- **Invalidation**: write-through via `Plan` model events — any Eloquent save with pricing-relevant changes (`base_price`, `included_units`, `overage_rate`, `billing_cycle_days`) or deletion forgets the key, regardless of the caller (controller, seeder, tinker).
- **Billing safety**: `BillingService` prices every segment through the cache — one snapshot per plan per run. Stale-cache windows are eliminated by invalidation, not hoped away.

Both behaviors are test-proven: mutation → fresh value; second read → zero queries.

---

## Rate Limiting

| Limiter          | Scope                  | Default | Config                                  |
| ---------------- | ---------------------- | ------- | --------------------------------------- |
| `usage`          | per merchant (API key) | 600/min | `billing.ingestion.merchant_per_minute` |
| `usage-customer` | per merchant+customer  | 60/min  | `billing.ingestion.customer_per_minute` |

Exceeding either returns `429` with `Retry-After`. Values are config-driven for per-environment tuning. Rationale: the merchant bucket caps aggregate API load per tenant; the customer bucket prevents a single hot customer from starving the merchant's budget.

---

## Dashboard Metrics

All metrics read `daily_usage` only.

1. **Top customers**: current-month totals, desc, limit 5.
2. **Projected overage revenue** (per active subscription): linear projection
   `projected_usage = usage_to_date / days_elapsed × cycle_days` with a day-one guard (`max(1, days_elapsed)`). An **estimate for the dashboard only** — never stored, never billed.
3. **Churn risk**: >50% month-over-month drop (`billing.dashboard.churn_drop_threshold`). **Previous-month = 0 is excluded** — no divide-by-zero, no false flags for brand-new customers.

---

## Testing

```bash
php artisan test --compact          # everything
vendor/bin/pest tests/Unit          # proration math only
```

### Coverage map (Plan §10)

| Area                                                                                                                                             | File                                               |
| ------------------------------------------------------------------------------------------------------------------------------------------------ | -------------------------------------------------- |
| Proration math (rounding, overage micros)                                                                                                        | `tests/Unit/ProrationServiceTest.php`              |
| Ingestion: idempotency, validation, 401/422/429, tenant isolation, subscription coverage                                                         | `tests/Feature/Api/RecordUsageTest.php`            |
| Aggregation: summing, chunk boundaries, watermark re-runs, duplicate dispatch, API→daily_usage                                                   | `tests/Feature/Aggregation/AggregationTest.php`    |
| Billing matrix: within/at/above allowance, zero usage, proration, multi-segment, upgrade, downgrade, invoice idempotency, finalized immutability | `tests/Feature/Billing/BillingTest.php`            |
| CRUD + plan-change segments + boundary rule + tenant isolation                                                                                   | `tests/Feature/Api/SubscriptionPlanChangeTest.php` |
| Dashboard API: top-5, projection math, day-one guard, churn edges, tenant isolation, cache invalidation + hit                                    | `tests/Feature/Dashboard/DashboardApiTest.php`     |
| Dashboard UI props                                                                                                                               | `tests/Feature/Dashboard/BillingDashboardTest.php` |
| Factories standalone                                                                                                                             | `tests/Feature/FactoriesTest.php`                  |

Tests run on in-memory SQLite with a sync queue — fully portable SQL (partial indexes, `ON CONFLICT` upserts verified on both SQLite and PostgreSQL).

---

## Assumptions & Trade-offs

| Decision                                    | Rationale / Trade-off                                                              |
| ------------------------------------------- | ---------------------------------------------------------------------------------- |
| **Merchant = existing `teams`**             | Reuses membership, slugs, middleware; no duplicate tenancy                         |
| **API keys, not Sanctum tokens**            | Simple machine-to-machine auth; sha-256 hashed, printed once                       |
| **Usage `type` not billed separately**      | One aggregate allowance per subscription (schema has no per-type dimension)        |
| **DB-constraint idempotency**               | App-level checks are a fast path only — the constraint is the guarantee            |
| **Integer cents + half-up per line**        | Deterministic money math; totals = Σ rounded lines                                 |
| **Half-open `[start, end)` segments**       | No gap days between segments; boundary changes replace segments                    |
| **Seeded `daily_usage` directly**           | Derived table, rebuildable from events; seeding events too would double write cost |
| **Sync invoice generation on the endpoint** | Immediate feedback + testability; the queued job exists for batch paths            |
| **Linear projection**                       | Simple, documented assumption; dashboards only                                     |
| **SQLite-portable SQL**                     | Tests stay fast/hermetic; verified against PostgreSQL semantics                    |

---

## Future Improvements

- Monthly partitioning + retention policy for `usage_events`
- Horizon for queue observability; `billing` queue dedicated to invoice jobs
- Bulk ingestion endpoint (batch arrays) for bursty producers
- Webhook/event notifications for invoice finalization
- Payment integration + `paid` transition workflow
- Read replica routing for dashboard queries
- Per-usage-type pricing dimensions

---

## Project Layout

```text
app/
  Actions/Billing|Usage/      # single-purpose business operations
  Data/                       # DTOs (PlanPricing, …)
  Enums/                      # SubscriptionStatus, InvoiceStatus, TeamRole, …
  Jobs/                       # AggregateDailyUsageJob, GenerateInvoiceJob
  Models/                     # Plan, Customer, Subscription, SubscriptionSegment,
                              # UsageEvent, DailyUsage, Invoice, InvoiceItem, ApiKey
  Services/                   # BillingService, ProrationService, DashboardService,
                              # PlanPricingCache
  Http/Controllers/Api/       # thin, merchant-scoped controllers
resources/js/
  components/billing/         # MetricSection, TopCustomers, ProjectedOverage, ChurnRisk
  types/billing.ts            # shared payload types
thoughts/shared/plans/        # phase-by-phase implementation briefs (living docs)
```
