<?php

declare(strict_types=1);

namespace App\Actions\Billing;

use App\Enums\SubscriptionStatus;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionSegment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CreateSubscriptionAction
{
    /**
     * Create a subscription and its initial plan segment.
     *
     * The initial segment spans [starts_at, starts_at + cycle_days) — the
     * exclusive end doubles as the first cycle boundary (Plan §3.2).
     *
     * @param  array{customer_id: int, plan_id: int, starts_at?: string}  $data
     *
     * @throws ValidationException When the customer or plan is not owned by the
     *                             merchant, or the plan is inactive.
     */
    public function handle(int $merchantId, array $data): Subscription
    {
        /** @var Customer $customer */
        $customer = Customer::query()
            ->where('merchant_id', $merchantId)
            ->findOrFail($data['customer_id']);

        /** @var Plan $plan */
        $plan = Plan::query()
            ->where('merchant_id', $merchantId)
            ->findOrFail($data['plan_id']);

        if (! $plan->is_active) {
            throw ValidationException::withMessages(['plan_id' => 'plan_inactive']);
        }

        $startsAt = Carbon::parse($data['starts_at'] ?? today()->toDateString());
        $cycleEnd = $startsAt->copy()->addDays($plan->billing_cycle_days);

        return DB::transaction(function () use ($customer, $plan, $startsAt, $cycleEnd): Subscription {
            $subscription = Subscription::query()->create([
                'customer_id' => $customer->id,
                'plan_id'     => $plan->id,
                'merchant_id' => $customer->merchant_id,
                'starts_at'   => $startsAt->toDateString(),
                'ends_at'     => null,
                'status'      => SubscriptionStatus::Active->value,
            ]);

            SubscriptionSegment::query()->create([
                'subscription_id' => $subscription->id,
                'plan_id'         => $plan->id,
                'starts_at'       => $startsAt->toDateString(),
                'ends_at'         => $cycleEnd->toDateString(),
            ]);

            return $subscription;
        });
    }
}
