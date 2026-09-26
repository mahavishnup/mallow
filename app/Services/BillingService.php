<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Models\DailyUsage;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionSegment;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Builds invoices from subscription_segments + daily_usage (Plan §4, §6).
 *
 * Never reads usage_events: daily_usage is the billing-grade aggregate, and
 * segments are the only authority for "which plan covers which day".
 */
final class BillingService
{
    public function __construct(
        private readonly ProrationService $proration,
        private readonly PlanPricingCache $pricingCache,
    ) {}

    /**
     * Segments overlapping [periodStart, periodEnd), clipped to the period.
     *
     * Zero-length clips are skipped (D3.4: a change effective exactly on a
     * cycle boundary produces one full segment, not a degenerate zero-day one).
     *
     * @return Collection<int, SubscriptionSegment>
     */
    public function segmentsForPeriod(Subscription $subscription, CarbonInterface $start, CarbonInterface $end): Collection
    {
        return $subscription->segments()
            ->where('starts_at', '<', $end->toDateString())
            ->where('ends_at', '>', $start->toDateString())
            ->get()
            ->map(fn (SubscriptionSegment $segment): SubscriptionSegment => $segment
                ->forceFill([
                    'starts_at' => $segment->starts_at->max($start),
                    'ends_at'   => $segment->ends_at->min($end),
                ]))
            ->filter(fn (SubscriptionSegment $segment): bool => $segment->starts_at->lessThan($segment->ends_at))
            ->values();
    }

    /**
     * Total usage between two dates (half-open), from daily_usage only.
     */
    public function segmentUsage(int $customerId, CarbonInterface $start, CarbonInterface $end): int
    {
        return (int) DailyUsage::query()
            ->where('customer_id', $customerId)
            ->where('usage_date', '>=', $start->toDateString())
            ->where('usage_date', '<', $end->toDateString())
            ->sum('total_quantity');
    }

    /**
     * Build (or rebuild a draft of) the invoice for [periodStart, periodEnd).
     *
     * Idempotent via the unique (subscription_id, period_start) constraint
     * (D3.5): an existing finalized/paid invoice is returned untouched; a
     * draft is rebuilt from current data.
     */
    public function buildInvoice(Subscription $subscription, CarbonInterface $periodStart, CarbonInterface $periodEnd): Invoice
    {
        return DB::transaction(function () use ($subscription, $periodStart, $periodEnd): Invoice {
            /** @var Invoice|null $existing */
            $existing = Invoice::query()
                ->where('subscription_id', $subscription->id)
                ->whereDate('period_start', $periodStart->toDateString())
                ->first();

            if ($existing !== null && $existing->status !== InvoiceStatus::Draft) {
                return $existing;
            }

            $invoice = $existing ?? new Invoice([
                'subscription_id' => $subscription->id,
                'merchant_id'     => $subscription->merchant_id,
                'customer_id'     => $subscription->customer_id,
                'period_start'    => $periodStart->toDateString(),
                'period_end'      => $periodEnd->toDateString(),
            ]);

            $items = $this->buildItems($subscription, $periodStart, $periodEnd);
            $totalCents = $items->sum(fn (array $item): int => $item['line_total_cents']);

            $invoice->fill([
                'total_amount' => number_format($totalCents / 100, 2, '.', ''),
                'status'       => InvoiceStatus::Draft->value,
            ]);
            $invoice->save();

            // Rebuild: replace draft items with the freshly computed set.
            $invoice->items()->delete();

            foreach ($items as $item) {
                $invoice->items()->create([
                    'plan_id'        => $item['plan_id'],
                    'segment_start'  => $item['segment_start'],
                    'segment_end'    => $item['segment_end'],
                    'prorated_base'  => $item['prorated_base_cents'] / 100,
                    'included_units' => $item['included_units'],
                    'billable_usage' => $item['billable_usage'],
                    'overage_units'  => $item['overage_units'],
                    'overage_amount' => $item['overage_cents'] / 100,
                    'line_total'     => $item['line_total_cents'] / 100,
                ]);
            }

            return $invoice->refresh();
        });
    }

    /**
     * Compute the per-segment billing lines for a period.
     *
     * @return Collection<int, array{
     *     plan_id: int,
     *     segment_start: string,
     *     segment_end: string,
     *     prorated_base_cents: int,
     *     included_units: int,
     *     billable_usage: int,
     *     overage_units: int,
     *     overage_cents: int,
     *     line_total_cents: int,
     * }>
     */
    private function buildItems(Subscription $subscription, CarbonInterface $periodStart, CarbonInterface $periodEnd): Collection
    {
        return $this->segmentsForPeriod($subscription, $periodStart, $periodEnd)
            ->map(function (SubscriptionSegment $segment) use ($subscription): array {
                /** @var Plan $plan */
                $plan = Plan::query()->findOrFail($segment->plan_id);

                // Pricing read goes through the cache — one snapshot per plan
                // per run (Plan §7 billing safety).
                $pricing = $this->pricingCache->get($plan);

                $activeDays = (int) $segment->starts_at->diffInDays($segment->ends_at);

                $proratedBaseCents = $this->proration->proratedBaseCents(
                    $pricing->basePriceCents,
                    $activeDays,
                    $pricing->cycleDays,
                );

                $proratedIncluded = $this->proration->proratedIncludedUnits(
                    $pricing->includedUnits,
                    $activeDays,
                    $pricing->cycleDays,
                );

                $usage = $this->segmentUsage($subscription->customer_id, $segment->starts_at, $segment->ends_at);
                $overageUnits = max(0, $usage - $proratedIncluded);
                $overageCents = $this->proration->overageCents(
                    $overageUnits,
                    $pricing->overageRateMicros,
                );

                return [
                    'plan_id'             => $segment->plan_id,
                    'segment_start'       => $segment->starts_at->toDateString(),
                    'segment_end'         => $segment->ends_at->toDateString(),
                    'prorated_base_cents' => $proratedBaseCents,
                    'included_units'      => $proratedIncluded,
                    'billable_usage'      => $usage,
                    'overage_units'       => $overageUnits,
                    'overage_cents'       => $overageCents,
                    'line_total_cents'    => $proratedBaseCents + $overageCents,
                ];
            });
    }
}
