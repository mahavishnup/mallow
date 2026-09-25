# Phase 1 — Usage Ingestion API

> Prereqs: Phase 0 complete. · Follows: Plan §5.1, §8, §11 Phase 1 · Unlocks: Phase 2
> Goal: idempotent `POST /api/usage` with API-key auth, validation, tenant isolation, and merchant/customer rate limiting. Events land in `usage_events` un-aggregated.

---

## 1. Scope

**In scope**

- `usage_events` migration (all indexes, Plan §3.2/§3.3)
- `routes/api.php` + registration in `bootstrap/app.php`
- `ResolveApiKey` middleware (X-Api-Key → merchant Team)
- `CreateApiKeyCommand` (mint keys for testing/demo)
- `RecordUsageRequest` form request + `UsageController::store`
- `RecordUsageAction` (idempotent insert)
- Rate limiters `usage` (merchant-wide) + `usage-customer`
- Feature tests: idempotency, validation, 429, tenant isolation, subscription coverage

**Out of scope**

- `daily_usage` writes (Phase 2) · any aggregation (Phase 2) · invoices (Phase 3)

---

## 2. Tasks

### 2.1 Migration — `usage_events` (Plan §3.2/§3.3)

- [x] Columns: `id`, `uuid` nullable uuid **unique** (alias for idempotency key; keep column name `uuid`), `merchant_id` FK, `customer_id` FK, `usage_date date`, `quantity unsignedBigInteger`, `type string`, `aggregated_at timestamp nullable`, timestamps.
- [x] Indexes: unique `(uuid)`; `(customer_id, usage_date)`; `(merchant_id, usage_date)`; partial index on `(aggregated_at)` **`WHERE aggregated_at IS NULL`** — sqlite supports partial indexes, keep syntax portable.
- [x] Verify partial index SQL runs on sqlite (test) and local DB.

### 2.2 API plumbing

- [x] Create `routes/api.php` (empty group to start). Register in `bootstrap/app.php`: `->withRouting(api: …, commands: …, health: …)` (C0.9). — Note: `withRouting(api:)` auto-prefixes `/api` + applies the `api` group (bindings only in L13); no manual `->prefix('api')` needed.
- [x] `app/Http/Middleware/ResolveApiKey.php`: reads `X-Api-Key` header → sha-256 → `ApiKey::where('key_hash', hash('sha256', $key))->first()` with merchant relation → binds merchant Team on the request (`$request->setUserResolver(...)` or a shared `MerchantContext` DTO held on request attributes). Missing/invalid key → `401 {"error": "invalid_api_key"}`. On success, touch `last_used_at` (throttled — don't write on every request; e.g., only if > 60s since last).
- [x] `app/Console/Commands/CreateApiKeyCommand.php` (`billing:api-key {--merchant=} {--name=}`): creates key, prints **plaintext once** (`mall_` prefix + random 32 bytes, hex). Never store plaintext.
- [x] Rate limiters in `AppServiceProvider::boot()` (Plan §8): `RateLimiter::for('usage', …)` keyed by `$request->userResolver()/merchant id` (600/min) and `RateLimiter::for('usage-customer', …)` keyed `merchant:{id}:customer:{customer_id}` (60/min). Values from `config('billing.ingestion')`. Include `Retry-After` header (default `ResponseException` behavior handles it).

### 2.3 Form Request — `app/Http/Requests/Api/RecordUsageRequest`

- [x] Rules: `customer_id` required integer exists:customers,id · `usage_date` required date format `Y-m-d` (reject future dates: `after_or_equal:today` is wrong direction — use `before_or_equal:today` if we don't allow future usage; **decision: allow only today-or-past**, `usage_date|date|before_or_equal:today`) · `quantity` required integer min:1 max:2^53-1 (JSON-safe) · `type` required string max:64 (alpha-dash) · `idempotency_key` required uuid.
- [x] JSON error responses: ensure `shouldRenderJsonWhen` (already set in `bootstrap/app.php`) covers `api/*` → 422 shape stays Laravel default.

### 2.4 Action — `app/Actions/Billing/RecordUsageAction.php`

Signature idea (adjust to codebase style):

```php
final class RecordUsageAction
{
    /** @return array{stored: bool} — stored=false means duplicate idempotency key */
    public function handle(Team $merchant, array $data): array;
}
```

- [x] Fast path: `UsageEvent::where('uuid', $idempotencyKey)->exists()` → return `stored: false` early (saves a write attempt).
- [x] Insert inside try/catch on unique violation (QueryException with SQLSTATE 23505 on PG / 23000-ish on sqlite) → treat as duplicate, return `stored: false`. **The DB constraint is the real guard** (Plan §5.1 decision).
- [x] **Tenant check** (D1.2 + Plan §5.1 flow): load customer scoped `Customer::where('id', …)->where('merchant_id', $merchant->id)->firstOrFail()` → 404/422 if not owned. Then verify an **active subscription covering `usage_date`** exists (segments overlap check may be Phase 3; here check `subscriptions.status = active` and `starts_at <= usage_date` and (`ends_at` null or `> usage_date`)) → else 422 `no_active_subscription`.
- [x] On successful insert → `AggregateDailyUsageJob::dispatch($customer, $usageDate)->afterCommit()` — but the Job class arrives in Phase 2. **Stub strategy:** dispatch a closure-free queued job by dispatching `AggregateDailyUsageJob` only after it exists; in this phase, simply leave a `// Phase 2: dispatch AggregateDailyUsageJob (afterCommit)` TODO and do not dispatch. Tests for aggregation live in Phase 2.
- [x] Keep transaction scope tiny: single insert, no sync aggregation (Plan §5.1).

### 2.5 Controller — `app/Http/Controllers/Api/UsageController.php`

```text
POST /api/usage   [throttle:usage, throttle:usage-customer, ResolveApiKey]
```

- [x] Validate → action → respond. `201 {data: {…}}` on stored; `200 {duplicate: true}` on duplicate (Plan §5.1). Never 4xx on duplicate — client retries must be safe.
- [x] 429 and 401 come from middleware/throttle; no controller logic needed.

### 2.6 Tests — `tests/Feature/Api/RecordUsageTest.php` (Pest)

Cover Plan §10 "API / Platform" rows:

- [x] valid request → 201, event row exists with `aggregated_at = null`
- [x] identical retry (same `idempotency_key`) → 200 `{duplicate:true}`, still **1 row**
- [x] missing/invalid `X-Api-Key` → 401
- [x] validation failures (bad date, qty 0/negative/string, missing key) → 422
- [x] usage_date in the future → 422
- [x] tenant isolation: merchant B's key + merchant A's customer → 422/404, no row
- [x] customer without active subscription → 422
- [x] rate limit: exceed merchant bucket → 429 (use `RateLimiter::clear` between tests; small custom config override in test to e.g. 2/min for speed)
- [x] per-customer limit independent of merchant limit

## 3. Acceptance Criteria

- [x] `php artisan route:list --path=api` shows `POST api/usage` with both throttles + key middleware. — Verified via `route:list -v`: `api` → `ResolveApiKey` → `throttle:usage` → `throttle:usage-customer`.
- [x] Duplicate submission never creates a second row (DB-level proof: attempt direct duplicate insert in a test → constraint violation caught → `duplicate:true`).
- [x] 401/422/429 paths tested. Tenant isolation proven.
- [x] Pint + PHPStan clean; tests green. — 16/16 phase tests; full suite 113/113 (406 assertions).

## 4. Notes & Links

- Rate limiter config values in `config/billing.php` (Phase 0) — read, don't hardcode.
- `Retry-After` header: Laravel's throttle middleware adds it automatically on 429 via `RateLimiter::for` definitions.
- Keep controller thin — all logic in the action.
