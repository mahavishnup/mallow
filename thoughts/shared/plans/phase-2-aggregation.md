# Phase 2 — Queued Aggregation

> Prereqs: Phase 1 complete. · Follows: Plan §6, §11 Phase 2 · Unlocks: Phase 3
> Goal: `usage_events` → `daily_usage` aggregation that is chunked, watermarked (`aggregated_at`), idempotent on re-run, and safe under duplicate dispatch.

---

## 1. Scope

**In scope**

- `daily_usage` migration (unique `(customer_id, usage_date)`, indexes)
- `UsageEvent` + `DailyUsage` models (c/f Phase 0 style)
- `AggregateDailyUsageAction` (chunked, watermarked upsert)
- `AggregateDailyUsageJob` (queued; dispatched afterCommit from `RecordUsageAction` — wire the Phase 1 TODO)
- Queue config + worker instructions
- Tests: chunking, idempotent re-runs, multi-customer isolation, same-day multi-event summing

**Out of scope**

- Billing reads `daily_usage` (Phase 3) · dashboard reads (Phase 4) · any pruning of events

---

## 2. Tasks

### 2.1 Migration — `daily_usage` (Plan §3.2)

- [ ] Columns: `id`, `merchant_id` FK, `customer_id` FK, `use_date`… **name it `usage_date date`**, `total_quantity unsignedBigInteger`, `event_count unsignedInteger`, timestamps.
- [ ] **Unique `(customer_id, usage_date)`** (upsert target). Index `(merchant_id, usage_date)`.

### 2.2 Models

- [ ] `UsageEvent` — casts `usage_date: date`, `aggregated_at: datetime`; relations `customer()`, `merchant()`. Scope `unaggregated()` (`whereNull('aggregated_at')`).
- [ ] `DailyUsage` — casts `usage_date: date`; relations `customer()`, `merchant()`. Factory with state for a given customer/date/qty.

### 2.3 Action — `app/Actions/Usage/AggregateDailyUsageAction.php`

Algorithm (Plan §6):

```text
UsageEvent::query()
    ->whereNull('aggregated_at')
    ->orderBy('id')
    ->chunkById(config('billing.aggregation.chunk_size'), function (Collection $chunk): void {
        DB::transaction(function () use ($chunk): void {
            // group by (customer_id, usage_date)
            // upsert daily_usage with incremental update:
            //   total_quantity = total_quantity + Δ, event_count = event_count + n
            // mark chunk events aggregated_at = now()
        });
    });
```

- [ ] Use `upsert()` with raw increment expression, or `INSERT … ON CONFLICT (customer_id, usage_date) DO UPDATE SET total_quantity = daily_usage.total_quantity + excluded.total_quantity …` — **verify the expression syntax works identically on sqlite and PostgreSQL**; if not, use portable per-row `DailyUsage::updateOrCreate` + lock. Prefer the single-statement upsert for atomicity under duplicate dispatch (Plan §6 "row-level locking on upsert").
- [ ] Increment math must derive Δ from the chunk only. The watermark advances **in the same transaction** as each chunk's upsert (Plan §6 retry-safety).
- [ ] Concurrency: two workers picking the same rows — the upsert `ON CONFLICT DO UPDATE` with `+ excluded.` increment keeps totals correct; marking `aggregated_at` uses `whereNull('aggregated_at')` guard on update so events are counted once.
- [ ] Alternative (rejected): naive `chunk()` with offset — violates Plan §6 chunkById requirement.
- [ ] `AggregateDailyUsageAction` is callable from a scheduled command or tinker for backfills.

### 2.4 Job — `app/Jobs/AggregateDailyUsageJob.php`

- [ ] Payload: optionally scoped to `(customer_id, usage_date)` when dispatched from ingestion (aggregates just that day — small fast job), or unscoped (full sweep) when run scheduled/manual. Constructor: `public function __construct(public ?int $customerId = null, public ?string $usageDate = null)`.
- [ ] `handle(AggregateDailyUsageAction $action): void` → delegates.
- [ ] Queue: `default`. Backoff/extries: `$tries = 3`, backoff 10s.
- [ ] Dispatch wiring (Phase 1 TODO): in `RecordUsageAction` on successful insert → `AggregateDailyUsageJob::dispatch(customerId, usage_date)->afterCommit()`. Tests use `QUEUE_CONNECTION=sync` so it runs inline — **assert `daily_usage` updated after POST** in the Phase 2 tests (moves one Phase 1 assertion forward; keep the Phase 1 duplicate/no-double-count tests intact).
- [ ] Ingestion dispatch decision: per-day scoped dispatch keeps jobs small and enables parallelism per (customer, day); full sweep remains available. _(Decision D2.1)_

### 2.5 Queue config & ops

- [ ] `.env.example`: document `QUEUE_CONNECTION=database` as default local (C0.8: Redis if available: `redis` + `CACHE_STORE=redis`); README details land in Phase 6.
- [ ] Schedule optional safety-net sweep in `routes/console.php`: `Schedule::daily()->at('02:00')->job(new AggregateDailyUsageJob)` — catches any unmarked stragglers (Plan §6 retry story).

### 2.6 Tests — `tests/Feature/Aggregation/AggregationTest.php`

Cover Plan §10 "Aggregation" rows:

- [ ] single event → correct daily total + event_count 1
- [ ] multiple events same customer/day → summed (incl. from separate POSTs)
- [ ] multiple customers → isolated totals (no cross-contamination)
- [ ] chunk boundary: create 2× chunk-size+ε events (override `config(['billing.aggregation.chunk_size' => 5])`), run action → totals correct across chunks
- [ ] idempotent re-run: run action twice → totals unchanged (watermark works)
- [ ] duplicate POST (same idempotency key, via API) → no double count in `daily_usage`
- [ ] concurrent-marking guard: manually re-mark an event's `aggregated_at` null and re-run → only that event's Δ re-applied **or** skip if already counted — assert totals remain consistent with the guard semantics you implement (document in test comment)

## 3. Acceptance Criteria

- [ ] Re-running `AggregateDailyUsageAction` any number of times never changes `daily_usage` totals (watermark proof).
- [ ] Chunked processing handles >chunk-size events correctly.
- [ ] POST /api/usage → worker (sync in tests) → `daily_usage` row correct; duplicate POST leaves it unchanged.
- [ ] Pint + PHPStan clean; tests green.

## 4. Notes & Links

- Partial index from Phase 1 (`WHERE aggregated_at IS NULL`) keeps the watermark query cheap at 5M rows.
- Never scan raw events for billing/dashboard (Plan §2 principle) — Phase 3/4 read `daily_usage` only.
