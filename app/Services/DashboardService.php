<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DailyUsage;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Team;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Merchant dashboard metrics (Plan §5.2), computed exclusively from
 * daily_usage — never raw usage events.
 */
final class DashboardService
{
    public function __construct(
        private readonly ProrationService $proration,
        private readonly PlanPricingCache $pricingCache,
    ) {}

    /**
     * Top customers by total usage in a calendar month (default: current UTC month).
     *
     * @return Collection<int, array{customer_id: int, name: string, total_quantity: int}>
     */
    public function topCustomers(Team $merchant, ?CarbonInterface $month = null): Collection
    {
        $month ??= now()->utc();
        $start = $month->copy()->startOfMonth()->toDateString();
        $end = $month->copy()->addMonth()->startOfMonth()->toDateString();

        // Half-open [start, end): >= first day of month, < first day of next month.
        // Avoids counting the boundary date in both months if a query runs exactly
        // on month-end midnight (consistent with every other date range in this app).
        $rows = DB::table('daily_usage')
            ->where('daily_usage.merchant_id', $merchant->id)
            ->where('daily_usage.usage_date', '>=', $start)
            ->where('daily_usage.usage_date', '<', $end)
            ->join('customers', 'customers.id', '=', 'daily_usage.customer_id')
            ->groupBy('daily_usage.customer_id', 'customers.name')
            ->selectRaw('daily_usage.customer_id as customer_id, customers.name as name, SUM(daily_usage.total_quantity) as total_quantity')
            ->orderByDesc('total_quantity')
            ->limit(5)
            ->get();

        return $rows->map(fn (stdClass $row): array => [
            'customer_id'    => (int) $row->customer_id,
            'name'           => (string) $row->name,
            'total_quantity' => (int) $row->total_quantity,
        ]);
    }

    /**
     * Projected overage revenue across the merchant's active subscriptions.
     *
     * Linear projection (documented assumption): projected usage to cycle end =
     * usage_to_date / days_elapsed × cycle_days. Day-one guard: days_elapsed is
     * clamped to a minimum of 1.
     *
     * @return array{total_cents: int, subscriptions: Collection<int, array{
     *     subscription_id: int, customer_name: string, usage_to_date: int,
     *     projected_usage: int, included_units: int,
     *     projected_overage_units: int<0, max>, projected_overage_cents: int,
     * }>}
     */
    public function projectedOverageRevenue(Team $merchant): array
    {
        $rows = Subscription::query()
            ->where('merchant_id', $merchant->id)
            ->where('status', 'active')
            ->with('customer:id,name')
            ->get()
            ->map(function (Subscription $subscription): array {
                /** @var Plan $plan */
                $plan = $subscription->plan;

                $pricing = $this->pricingCache->get($plan);

                // Cycle-to-date bounds anchored at the subscription's cycle grid.
                $cycleDays = $pricing->cycleDays;
                $daysSinceStart = (int) $subscription->starts_at->diffInDays(today()->utc());
                $cycleIndex = intdiv($daysSinceStart, $cycleDays);
                $cycleStart = $subscription->starts_at->copy()->addDays($cycleIndex * $cycleDays);
                $cycleEnd = $cycleStart->copy()->addDays($cycleDays);

                $usageToDate = (int) DailyUsage::query()
                    ->where('customer_id', $subscription->customer_id)
                    ->where('usage_date', '>=', $cycleStart->toDateString())
                    ->where('usage_date', '<', today()->utc()->addDay()->toDateString())
                    ->sum('total_quantity');

                $daysElapsed = max(1, (int) $cycleStart->diffInDays(today()->utc()));

                $projectedUsage = $this->proration->roundHalfUp($usageToDate * $cycleDays, $daysElapsed);
                $projectedOverageUnits = max(0, $projectedUsage - $pricing->includedUnits);
                $projectedOverageCents = $this->proration->overageCents(
                    $projectedOverageUnits,
                    $pricing->overageRateMicros,
                );

                return [
                    'subscription_id'         => $subscription->id,
                    'customer_name'           => $subscription->customer->name,
                    'usage_to_date'           => $usageToDate,
                    'projected_usage'         => $projectedUsage,
                    'included_units'          => $pricing->includedUnits,
                    'projected_overage_units' => $projectedOverageUnits,
                    'projected_overage_cents' => $projectedOverageCents,
                ];
            });

        return [
            'total_cents'   => (int) $rows->sum('projected_overage_cents'),
            'subscriptions' => $rows,
        ];
    }

    /**
     * Customers whose usage dropped more than the configured MoM threshold.
     *
     * Convention: previous-month usage of exactly 0 is excluded (no
     * divide-by-zero, no false churn flags for brand-new customers).
     *
     * @return Collection<int, array{customer_id: int, name: string, previous_month: int, current_month: int, drop_percentage: float}>
     */
    public function churnRiskCustomers(Team $merchant): Collection
    {
        $threshold = (float) config('billing.dashboard.churn_drop_threshold');

        $now = now()->utc();
        $currentStart = $now->copy()->startOfMonth()->toDateString();
        $currentEnd = $now->copy()->addMonth()->startOfMonth()->toDateString();
        $previousStart = $now->copy()->subMonth()->startOfMonth()->toDateString();

        $rows = DB::table('daily_usage')
            ->where('daily_usage.merchant_id', $merchant->id)
            ->where(fn ($query) => $query
                ->whereBetween('daily_usage.usage_date', [$currentStart, $currentEnd])
                ->orWhereBetween('daily_usage.usage_date', [$previousStart, $currentStart]))
            ->join('customers', 'customers.id', '=', 'daily_usage.customer_id')
            ->groupBy('daily_usage.customer_id', 'customers.name')
            ->selectRaw('
                daily_usage.customer_id as customer_id,
                customers.name as name,
                SUM(CASE WHEN daily_usage.usage_date >= ? THEN daily_usage.total_quantity ELSE 0 END) as current_month,
                SUM(CASE WHEN daily_usage.usage_date < ? THEN daily_usage.total_quantity ELSE 0 END) as previous_month
            ', [$currentStart, $currentStart])
            ->get()
            ->filter(function (stdClass $row) use ($threshold): bool {
                $previous = (int) $row->previous_month;

                return $previous > 0
                    && ((int) $row->current_month) / $previous < (1 - $threshold);
            })
            ->map(fn (stdClass $row): array => [
                'customer_id'     => (int) $row->customer_id,
                'name'            => (string) $row->name,
                'previous_month'  => (int) $row->previous_month,
                'current_month'   => (int) $row->current_month,
                'drop_percentage' => round((1 - ((int) $row->current_month) / ((int) $row->previous_month)) * 100, 1),
            ])
            ->values();

        return $rows;
    }

    /**
     * Cycle usage overview: summed usage-to-date for all of the merchant's
     * active subscriptions, plus the total included units across their plans.
     * Powers the "Current Cycle Usage" stat card (x / y units).
     *
     * @return array{usage_to_date: int, included_units: int, usage_percentage: float}
     */
    public function cycleOverview(Team $merchant): array
    {
        $usageToDate = (int) DailyUsage::query()
            ->where('merchant_id', $merchant->id)
            ->where('usage_date', '>=', now()->utc()->startOfMonth()->toDateString())
            ->sum('total_quantity');

        $includedUnits = (int) Subscription::query()
            ->where('merchant_id', $merchant->id)
            ->where('status', 'active')
            ->get()
            ->sum(fn (Subscription $subscription): int => $this->pricingCache->get($subscription->plan)->includedUnits);

        return [
            'usage_to_date'    => $usageToDate,
            'included_units'   => $includedUnits,
            'usage_percentage' => $includedUnits > 0
                ? round(($usageToDate / $includedUnits) * 100, 1)
                : 0.0,
        ];
    }

    /**
     * Daily usage trend for the last N days (default 30), inclusive of today.
     * Zero-filled so gaps render as flat segments, not missing points.
     *
     * @return Collection<int, array{date: string, total_quantity: int}>
     */
    public function dailyUsageTrend(Team $merchant, int $days = 30): Collection
    {
        // Half-open [from, to): from = first day of the window, to = the day
        // AFTER the last wanted day. Same convention as topCustomers — an
        // inclusive `<=` breaks on sqlite, which stores dates with a
        // " 00:00:00" suffix that fails a Y-m-d string comparison.
        $from = now()->utc()->subDays($days - 1)->startOfDay();
        $fromDate = $from->toDateString();
        $to = now()->utc()->startOfDay()->addDay()->toDateString();

        $rows = DB::table('daily_usage')
            ->where('merchant_id', $merchant->id)
            ->where('usage_date', '>=', $fromDate)
            ->where('usage_date', '<', $to)
            ->groupBy('usage_date')
            ->selectRaw('usage_date, SUM(total_quantity) as total_quantity')
            ->orderBy('usage_date')
            ->get();

        // Zero-fill the full window so every day has a point. Normalise the
        // grouped key to Y-m-d: sqlite returns "Y-m-d H:i:s" for date columns
        // while PostgreSQL returns "Y-m-d" — compare only on the first 10
        // chars so the cursor keys match on both drivers.
        $indexed = $rows->keyBy(fn (stdClass $row): string => mb_substr((string) $row->usage_date, 0, 10));

        $trend = collect();
        $cursor = $from;

        // $to is exclusive (half-open interval) — stop the day before it.
        while ($cursor->lt($to)) {
            $key = $cursor->toDateString();
            $row = $indexed->get($key);

            $trend->push([
                'date'           => $key,
                'total_quantity' => $row !== null ? (int) $row->total_quantity : 0,
            ]);

            // CarbonImmutable: addDay returns a new instance — reassign.
            $cursor = $cursor->addDay();
        }

        return $trend;
    }

    /**
     * The merchant's currently active plan summary for the header stat card
     * (name, cadence, base price). Picks the plan with the most active
     * subscriptions as the "active" one — informational only.
     *
     * @return array{name: string, billing_cycle_days: int, base_price_cents: int}|null
     */
    public function activePlanSummary(Team $merchant): ?array
    {
        $plan = Subscription::query()
            ->where('merchant_id', $merchant->id)
            ->where('status', 'active')
            ->with('plan')
            ->get()
            ->groupBy('plan_id')
            ->sortByDesc(fn ($group) => $group->count())
            ->first()
            ?->first()?->plan;

        if ($plan === null) {
            return null;
        }

        $pricing = $this->pricingCache->get($plan);

        return [
            'name'               => $plan->name,
            'billing_cycle_days' => $pricing->cycleDays,
            'base_price_cents'   => $this->proration->roundHalfUp($pricing->basePriceCents, 1),
        ];
    }
}
