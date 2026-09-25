# Phase 0 — Foundations

> Prereqs: none. · Follows: Plan §11 Phase 0 · Unlocks: Phases 1–3
> Goal: config + the core domain schema (plans, customers, subscriptions, subscription_segments) with models, relations, factories, and a clean Pint/PHPStan baseline.

---

## 1. Scope

**In scope**

- `config/billing.php` (rate limits, chunk size, projection assumption)
- Migrations: `plans`, `customers`, `subscriptions`, `subscription_segments`, `api_keys`
- Models + relationships + casts + factories for the above
- Enums: `SubscriptionStatus`, `InvoiceStatus` (InvoiceStatus needed by migration order — create now, used in Phase 3)
- Pint + PHPStan clean

**Out of scope (later phases)**

- `usage_events` / `daily_usage` (Phase 1/2) · `invoices` / `invoice_items` (Phase 3) · any routes/controllers/actions · Redis wiring beyond `.env.example` documentation

---

## 2. Tasks

### 2.1 Config — `config/billing.php`

```php
return [
    'ingestion' => [
        'merchant_per_minute' => 600,   // Plan §8
        'customer_per_minute' => 60,    // Plan §8
    ],
    'aggregation' => [
        'chunk_size' => 1000,           // Plan §6
    ],
    'dashboard' => [
        'projection'    => 'linear',    // Plan §5.2 assumption
        'churn_drop_threshold' => 0.5,  // >50% MoM drop
    ],
    'cache' => [
        'plan_pricing_ttl' => 86400,    // 24h safety net, Plan §7
    ],
];
```

- [x] Create file. Access everywhere as `config('billing.…')` — never hardcode these numbers again.

### 2.2 Migrations (one file per table, conventional names)

- [x] **`plans`** (Plan §3.2): `id`, `merchant_id` (FK → teams, cascade), `name`, `base_price decimal(10,2)`, `included_units unsignedBigInteger`, `overage_rate decimal(10,4)`, `billing_cycle_days unsignedSmallInteger default 30`, `is_active boolean default true`, timestamps. Index `(merchant_id)`.
- [x] **`customers`**: `id`, `merchant_id` FK, `name`, `email`, timestamps. **Unique `(merchant_id, email)`** (Plan: "unique per merchant").
- [x] **`subscriptions`**: `id`, `customer_id` FK, `plan_id` FK, `starts_at date`, `ends_at date nullable`, `status` string + check constraint (`active`,`canceled` — store enum values; use `$table->string('status')->default(...)` + explicit check if portable), timestamps. Indexes: `(customer_id)`, `(merchant_id, status)` — note `merchant_id` is denormalized **here only for the dashboard lookup**; write it on create (source of truth is `customers.merchant_id`). Q0.2 resolved: **merchant_id added**, written on create.
- [x] **`subscription_segments`** (Plan §3.2): `id`, `subscription_id` FK, `plan_id` FK, `starts_at date`, `ends_at date` (exclusive bound, Plan §4.1), timestamps. Index `(subscription_id, starts_at)`. Add a **check `starts_at < ends_at`** if portable. — Note: schema builder has no portable CHECK support (SQLite can't ALTER ADD CONSTRAINT); enforced at application level in Phase 3 actions instead.
- [x] **`api_keys`** (D0.2): `id`, `merchant_id` FK, `name`, `key_hash string unique` (sha-256), `last_used_at timestamp nullable`, timestamps.
- [x] Verify `php artisan migrate` runs on **both** sqlite (tests) and the configured local DB. — Q0.1 resolved: `.env` → PostgreSQL `mallow` + Redis; migrations ran green on both.

### 2.3 Enums

- [x] `app/Enums/SubscriptionStatus.php`: `case Active = 'active'; case Canceled = 'canceled';` (string-backed; TitleCase case names per PHP rules).
- [x] `app/Enums/InvoiceStatus.php`: `case Draft; case Finalized; case Paid;` (Phase 3 uses it; migration for invoices lands then — enum file now is fine and harmless).

### 2.4 Models (follow `app/Models/Team.php` conventions: `final`, `#[Fillable]`, `@property` PHPDoc, relation generics)

- [x] `Plan` — relations: `belongsTo(Team, 'merchant_id')` named `merchant()`. Casts: `base_price => decimal:2`, `overage_rate => decimal:4`, `is_active => bool`. Add `public function pricing(): object` later (Phase 4 cache) — **not now**.
- [x] `Customer` — `merchant(): BelongsTo`, unique-per-merchant email enforced by DB. Relations for later phases may be added when those models exist.
- [x] `Subscription` — `customer()`, `plan()`, `segments(): HasMany` (ordered by `starts_at`), `merchant()` (via denormalized `merchant_id`). Cast `status => SubscriptionStatus`.
- [x] `SubscriptionSegment` — `subscription()`, `plan()`. Date casts on `starts_at`/`ends_at`.
- [x] `ApiKey` — `merchant()`, `last_used_at` cast. (`key_hash` hidden from serialization.)

### 2.5 Factories (one per model, `php artisan make:model …` flags or `make:factory`)

- [x] `PlanFactory`: sensible defaults — `base_price: 29.00`, `included_units: 100_000`, `overage_rate: 0.0025`, `billing_cycle_days: 30`, `is_active: true`. Add states: `inactive()`, `withPricing(float $base, int $units, float $rate)`.
- [x] `CustomerFactory`, `SubscriptionFactory` (state `active()` sets `starts_at` today-ish, status active), `SubscriptionSegmentFactory`, `ApiKeyFactory`. — Note: SubscriptionFactory resolves denormalized `merchant_id` from its customer.
- [x] Ensure `DatabaseSeeder` still runs green (`php artisan db:seed --no-interaction` on sqlite if needed) — factories must be creatable standalone (`Model::factory()->create()`). — Proven by `tests/Feature/FactoriesTest.php` (6 tests / 20 assertions, sqlite) + `migrate:fresh --seed` on PostgreSQL.

### 2.6 Hygiene

- [x] `vendor/bin/pint --dirty --format agent`
- [x] `vendor/bin/phpstan analyse` — clean baseline (level per `phpstan.neon`). — Also fixed a pre-existing PHPDoc error in `app/Concerns/ProfileValidationRules.php` (`Unique` rule implements `Stringable`, not `ValidationRule`).

---

## 3. Acceptance Criteria

- [x] `php artisan migrate` clean on sqlite + local DB; `migrate:fresh` works.
- [x] `php artisan db:seed` green; each factory creates standalone.
- [x] All five migrations exist with the Plan §3.2 columns/indexes (compare against the plan tables one final time).
- [x] Enums exist and are string/int-backed correctly.
- [x] Pint + PHPStan clean.
- [x] No usage/billing/invoice tables yet (scope discipline).

## 4. Open Questions (resolve before or during the phase)

| #    | Question                                                                                                                                    | Default if unresolved                                         |
| ---- | ------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------- |
| Q0.1 | Local DB is PostgreSQL per Plan — confirm `.env` actually points at PG and it's reachable; otherwise adjust C0.8 fallback notes             | Keep sqlite-compatible SQL regardless                         |
| Q0.2 | Should `subscriptions.merchant_id` denormalization be added (Plan lists `(merchant_id, status)` index but table has no merchant_id column)? | **Yes, add it** (index requires it); document write-on-create |

## 5. Deliverables

`config/billing.php` · 5 migrations · 2 enums · 5 models · 5 factories · clean lint/static-analysis baseline.
