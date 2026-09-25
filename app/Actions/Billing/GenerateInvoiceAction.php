<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Models\Invoice;
use App\Models\Subscription;
use App\Services\BillingService;
use Illuminate\Support\Carbon;

final class GenerateInvoiceAction
{
    public function __construct(private readonly BillingService $billing) {}

    /**
     * Generate (or rebuild the draft of) the invoice for a billing period.
     *
     * @param  array{period_start?: string, period_end?: string}  $data  Defaults to the
     *                                                                   current cycle.
     */
    public function handle(Subscription $subscription, array $data = []): Invoice
    {
        [$periodStart, $periodEnd] = $this->resolvePeriod($subscription, $data);

        return $this->billing->buildInvoice($subscription, $periodStart, $periodEnd);
    }

    /**
     * Resolve the billing period: explicit input or the current cycle.
     *
     * The current cycle is derived from the subscription's starts_at anchored
     * to the plan's cycle length.
     *
     * @param  array{period_start?: string, period_end?: string}  $data
     * @return array{0: Carbon, 1: Carbon}
     */
    private function resolvePeriod(Subscription $subscription, array $data): array
    {
        if (isset($data['period_start'], $data['period_end'])) {
            return [
                Carbon::parse($data['period_start']),
                Carbon::parse($data['period_end']),
            ];
        }

        $cycleDays = $subscription->plan->billing_cycle_days;
        $cycleCount = $subscription->starts_at->diffInDays(today()) / $cycleDays;

        $cycleIndex = (int) floor($cycleCount);
        $periodStart = $subscription->starts_at->copy()->addDays($cycleIndex * $cycleDays);
        $periodEnd = $periodStart->copy()->addDays($cycleDays);

        return [$periodStart, $periodEnd];
    }
}
