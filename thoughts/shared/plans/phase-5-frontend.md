# Phase 5 — React Dashboard UI

> Prereqs: Phase 4 complete. · Follows: Plan §9, §11 Phase 5 · Unlocks: Phase 6
> Goal: extend the existing team-scoped Inertia dashboard page with usage-insight sections (top-5 customers, projected overage revenue, churn risk) with loading/empty/error states.

---

## 1. Scope

**In scope**

- `DashboardController` (web, existing) passes `DashboardService` metrics as Inertia props (D5.1 — no client fetch)
- Dashboard page sections + shared presentational components under `resources/js/components/billing/*`
- Loading/empty/error states per section
- Wayfinder regeneration after route/controller changes

**Out of scope**

- New pages (metrics live on the existing `/{current_team}/dashboard`) · new API endpoints · charts libraries (tables/cards suffice per Plan §9 "UI polish is secondary")

---

## 2. Tasks

### 2.1 Server side

- [ ] Existing `app/Http/Controllers/DashboardController.php` (web) — resolve current team, call `DashboardService`, pass typed props: `usageTopCustomers`, `projectedOverage`, `churnRiskCustomers`, plus `billingMonth`.
- [ ] Keep inertia response shape additive — existing `pendingInvitations` prop and its tests must stay green (`tests/Feature/DashboardTest.php`).

### 2.2 Types

- [ ] TypeScript types for the props (match Phase 4 payload): place under `resources/js/types/billing.d.ts` (or existing types location per starter kit layout).

### 2.3 Components (`resources/js/components/billing/`)

- [ ] `top-customers.tsx` — table: rank, customer name, current-month usage (formatted number). Empty state: "No usage recorded this month."
- [ ] `projected-overage.tsx` — highlight card: total projected overage revenue (formatted currency) + per-subscription breakdown table. Empty state: "No active subscriptions."
- [ ] `churn-risk.tsx` — table: customer, previous month, current month, drop %. Color the drop red. Empty state: "No churn-risk customers detected." (Empty is the happy path here.)
- [ ] `metric-section.tsx` — shared wrapper: title, loading skeleton, empty slot, error state ("Failed to load — retry" with `router.reload`), consistent card styling.
- [ ] Styling: follow starter-kit patterns (existing dashboard page components, Tailwind classes already in use). No new dependencies.

### 2.4 Page wiring

- [ ] `resources/js/pages/dashboard.tsx` — render the three sections (server props; loading/error states still matter for initial load + `router.reload` flows).
- [ ] Header: merchant/team context + current cycle/billing month display.
- [ ] Ensure `npm run build` is green and page renders with seeded data (Phase 6 seeder may not exist yet — verify with a manually seeded fixture or tinker-created rows, or defer visual verification to Phase 6 demo).

### 2.5 Tests

- [ ] Extend `tests/Feature/DashboardTest.php` (or new `tests/Feature/Dashboard/BillingDashboardTest.php`): authenticated team member sees the three props with expected values from a seeded fixture.
- [ ] Guest/unauthorized → redirect (already covered by existing test — keep green).

## 3. Acceptance Criteria

- [ ] `/{team}/dashboard` renders all three sections with correct data from `DashboardService`.
- [ ] Loading/empty/error states present for each section.
- [ ] `npm run build` green; existing dashboard tests still pass; new props tests green.
- [ ] Wayfinder regenerated if any route/controller signature changed.

## 4. Notes & Links

- Follow the `inertia-react-development` skill when writing Inertia React code.
- Number/currency formatting: use `Intl.NumberFormat` — no new deps.
- All data flows as Inertia props (D5.1); the JSON endpoint from Phase 4 exists for the machine API and parity testing.
