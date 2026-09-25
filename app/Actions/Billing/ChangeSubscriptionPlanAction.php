<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionSegment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ChangeSubscriptionPlanAction
{
    /**
     * Switch a subscription to a new plan from an effective date.
     *
     * Closes the current segment at (effective − 1 day) and opens a new one
     * from the effective date to the current cycle end. A change effective
     * exactly on the cycle boundary replaces the open segment instead of
     * creating a zero-day one (D3.4 boundary rule).
     *
     * @param  array{plan_id: int, effective_date?: string}  $data
     *
     * @throws ValidationException When the plan is not owned/active, the
     *                             effective date is outside the open segment,
     *                             or the new plan equals the current plan.
     */
    public function handle(Subscription $subscription, array $data): Subscription
    {
        /** @var SubscriptionSegment $openSegment */
        $openSegment = $subscription->segments()->orderByDesc('starts_at')->firstOrFail();

        /** @var Plan $newPlan */
        $newPlan = Plan::query()
            ->where('merchant_id', $subscription->merchant_id)
            ->findOrFail($data['plan_id']);

        if (! $newPlan->is_active) {
            throw ValidationException::withMessages(['plan_id' => 'plan_inactive']);
        }

        if ($newPlan->id === $openSegment->plan_id) {
            throw ValidationException::withMessages(['plan_id' => 'plan_unchanged']);
        }

        $effectiveDate = Carbon::parse($data['effective_date'] ?? today()->toDateString());

        // Half-open intervals (C0.4): effective must fall inside (starts_at, ends_at).
        // effective == ends_at would belong to the next cycle and is rejected.
        if ($effectiveDate->lessThan($openSegment->starts_at) || $effectiveDate->greaterThanOrEqualTo($openSegment->ends_at)) {
            throw ValidationException::withMessages(['effective_date' => 'outside_current_segment']);
        }

        return DB::transaction(function () use ($subscription, $openSegment, $newPlan, $effectiveDate): Subscription {
            $cycleEnd = $openSegment->ends_at->toDateString();

            if ($effectiveDate->equalTo($openSegment->starts_at)) {
                // Boundary rule: replace the open segment wholesale — no zero-day segment.
                $openSegment->update(['plan_id' => $newPlan->id]);
            } else {
                // ends_at is exclusive, so the old segment closes AT the effective
                // date — no gap day between the two segments.
                $openSegment->update(['ends_at' => $effectiveDate->toDateString()]);

                // The new segment runs to the current cycle end (Plan §4.3:
                // a mid-cycle change splits the cycle; it does not restart it).
                SubscriptionSegment::query()->create([
                    'subscription_id' => $subscription->id,
                    'plan_id'         => $newPlan->id,
                    'starts_at'       => $effectiveDate->toDateString(),
                    'ends_at'         => $cycleEnd,
                ]);
            }

            $subscription->update(['plan_id' => $newPlan->id]);

            return $subscription->refresh();
        });
    }
}
