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

- [x] Existing `app/Http/Controllers/DashboardController.php` (web) — resolve current team, call `DashboardService`, pass typed props: `usageTopCustomers`, `projectedOverage`, `churnRiskCustomers`, plus `billingMonth`. — Teamless users get empty defaults instead of a 500.
- [x] Keep inertia response shape additive — existing `pendingInvitations` prop and its tests must stay green (`tests/Feature/DashboardTest.php`).

### 2.2 Types

- [x] TypeScript types for the props (match Phase 4 payload): `resources/js/types/billing.ts`, re-exported from `types/index.ts`.

### 2.3 Components (`resources/js/components/billing/`)

- [x] `top-customers.tsx` — table: rank, customer name, current-month usage (formatted number). Empty state: "No usage recorded this month."
- [x] `projected-overage.tsx` — highlight card: total projected overage revenue (formatted currency) + per-subscription breakdown table. Empty state: "No active subscriptions."
- [x] `churn-risk.tsx` — table: customer, previous month, current month, drop %. Color the drop red. Empty state: "No churn-risk customers detected." (Empty is the happy path here.)
- [x] `metric-section.tsx` — shared wrapper: title, loading skeleton, empty slot, error state ("Retry" with `router.reload`), consistent card styling (shadcn Card + Skeleton).
- [x] Styling: starter-kit patterns only (Card/Skeleton primitives, Tailwind, `Intl.NumberFormat`); no new dependencies.

### 2.4 Page wiring

- [x] `resources/js/pages/dashboard.tsx` — renders the three sections (server props; loading/error states still matter for initial load + `router.reload` flows). Placeholder cards replaced.
- [x] Header: merchant/team context + current cycle/billing month display.
- [x] `npm run build` green + `npx tsc --noEmit` clean; page data proven by `BillingDashboardTest` fixtures. — Visual check with seeded demo data deferred to Phase 6 (per this brief's allowance).

### 2.5 Tests

- [x] New `tests/Feature/Dashboard/BillingDashboardTest.php`: authenticated team member sees the three props with expected values from a seeded fixture (incl. churn ordering + drop math); teamless user gets empty props.
- [x] Guest/unauthorized → redirect (existing test — still green).

## 3. Acceptance Criteria

- [x] `/{team}/dashboard` renders all three sections with correct data from `DashboardService`.
- [x] Loading/empty/error states present for each section (shared `MetricSection` wrapper).
- [x] `npm run build` green; `tsc --noEmit` clean; existing dashboard tests still pass; new props tests green. — Full suite 159/159.
- [x] Wayfinder: no route/controller signature changed in this phase — regeneration not required.

## 4. Notes & Links

- Follow the `inertia-react-development` skill when writing Inertia React code.
- Number/currency formatting: use `Intl.NumberFormat` — no new deps.
- All data flows as Inertia props (D5.1); the JSON endpoint from Phase 4 exists for the machine API and parity testing.
