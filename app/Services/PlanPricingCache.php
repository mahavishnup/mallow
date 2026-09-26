<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\PlanPricing;
use App\Models\Plan;
use Illuminate\Support\Facades\Cache;

/**
 * Read-through cache for plan pricing (Plan §7).
 *
 * Key: plan:{id}:pricing → integer-priced DTO. Invalidated write-through on
 * every Eloquent save/delete of a Plan with pricing-relevant changes, so the
 * stale-cache window is eliminated regardless of the mutation's caller
 * (controller, seeder, tinker).
 */
final class PlanPricingCache
{
    /**
     * Cache key for a plan's pricing snapshot.
     */
    public static function keyFor(int $planId): string
    {
        return "plan:{$planId}:pricing";
    }

    /**
     * Get the plan's pricing, reading through the cache on miss.
     */
    public function get(Plan $plan): PlanPricing
    {
        /** @var array{base_price_cents: int, included_units: int, overage_rate_micros: int, cycle_days: int}|null $cached */
        $cached = Cache::get(self::keyFor($plan->id));

        if ($cached !== null) {
            return PlanPricing::fromArray($cached);
        }

        $pricing = new PlanPricing(
            basePriceCents: (int) round(((float) $plan->base_price) * 100),
            includedUnits: $plan->included_units,
            overageRateMicros: (new ProrationService)->rateToMicros($plan->overage_rate),
            cycleDays: $plan->billing_cycle_days,
        );

        Cache::put(
            self::keyFor($plan->id),
            $pricing->toArray(),
            (int) config('billing.cache.plan_pricing_ttl'),
        );

        return $pricing;
    }

    /**
     * Invalidate the cached pricing for a plan.
     */
    public function forget(int $planId): void
    {
        Cache::forget(self::keyFor($planId));
    }
}
