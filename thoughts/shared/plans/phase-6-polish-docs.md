# Phase 6 — Polish & Docs

> Prereqs: Phases 0–5 complete. · Follows: Plan §11 Phase 6, §12 · Final phase
> Goal: demo seeder, comprehensive README, full-suite verification from a clean database, Definition-of-Done sweep.

---

## 1. Scope

**In scope**

- `BillingDemoSeeder` (merchant, plans, customers, subscriptions, backdated usage, a mid-cycle plan change, an invoice)
- README (full outline per Plan §11 Phase 6)
- Clean-clone verification: fresh migrate + seed + test suite + frontend build
- Definition of Done (Plan §12) checklist sweep

**Out of scope**

- New features. Bug fixes only.

---

## 2. Tasks

### 2.1 Demo seeder — `database/seeders/BillingDemoSeeder.php`

- [ ] One merchant team (owner user `demo@example.com` / password `password` — printable on seed), 3–4 plans across price tiers, ~10 customers, subscriptions mixed: stable, mid-cycle **upgrade**, mid-cycle **downgrade**, one high-usage (overage), one near-zero usage (churn risk).
- [ ] Backdated `daily_usage` rows spanning current + previous month (churn metric needs prev-month data) — **seed `daily_usage` directly** (derived table; also seed matching `usage_events` with `aggregated_at` set for realism, or leave events out — decide and be consistent; direct daily_usage seeding is the fast path).
- [ ] One generated invoice for a completed cycle (via `BillingService`, not hand-written rows — proves the engine on demo data).
- [ ] Wire into `DatabaseSeeder` behind a check or run explicitly: `php artisan db:seed --class=BillingDemoSeeder --no-interaction`.
- [ ] Mint a demo API key via `billing:api-key` and print instructions.

### 2.2 README.md

Cover every Plan §11 Phase 6 bullet (use its outline; structure into sections):

- [ ] Project overview + architecture diagram (Plan §2 ASCII)
- [ ] Setup: prerequisites (PHP 8.4, PostgreSQL, Redis optional w/ fallbacks), `.env`, `composer install`, key generate, migrate, seed, `npm install && npm run build`, queue worker (`php artisan queue:work`), Herd note (`https://mallow.test`)
- [ ] API reference: auth (`X-Api-Key`), `POST /api/usage`, dashboard, plans/customers/subscriptions/invoice endpoints — with request/response examples
- [ ] Schema & index rationale (Plan §3.3 table + why), 5M+ scaling discussion (Plan §3.4: partitioning, archival, replicas, bulk insert)
- [ ] Idempotency (DB-constraint-first + idempotent duplicate response), queue/chunking/watermark design (Plan §6), cache strategy + invalidation (Plan §7)
- [ ] Billing & proration formulas (Plan §4.2 integer-domain versions from Phase 3 brief), upgrade/downgrade handling (segments)
- [ ] Rate limiting (values, rationale, config knobs), dashboard projection + churn conventions (prev-month-zero exclusion, linear projection)
- [ ] Testing instructions + coverage map (which test file covers which Plan §10 row)
- [ ] Assumptions & trade-offs (every `D*` decision from the plan briefs' shared conventions) + future improvements

### 2.3 Verification sweep (Plan §12 Definition of Done)

- [ ] Fresh DB: `php artisan migrate:fresh --seed --no-interaction` → green end to end
- [ ] API smoke (documented commands): record usage → identical retry → `daily_usage` unchanged (worker running)
- [ ] Invoice generation correct on demo data (spot-check totals by hand against formulas)
- [ ] Mid-cycle change demo: old usage at old rate, new usage at new rate
- [ ] Dashboard shows all three metrics
- [ ] `php artisan test --compact` green (full suite); `npm run build` green
- [ ] `vendor/bin/pint --dirty --format agent` + `vendor/bin/phpstan analyse` clean
- [ ] Update the master Status table in [`thoughts/shared/plans/README.md`](README.md) → all ✅; tick every phase brief's acceptance boxes

## 3. Acceptance Criteria

- [ ] A fresh clone, following README only, reaches a working dashboard with demo data.
- [ ] Every Plan §12 Definition-of-Done item demonstrably true.
- [ ] Full suite + build green from clean state.

## 4. Notes & Links

- README is explicitly requested by the plan — creating it is in scope (documentation-files rule satisfied).
- Keep the README honest: only document behaviors that have passing tests (e.g., rate-limit values, projection convention).
