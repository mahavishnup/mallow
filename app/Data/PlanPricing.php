<?php

declare(strict_types=1);

namespace App\Data;

/**
 * Immutable integer-domain pricing snapshot served by PlanPricingCache.
 *
 * @see \App\Services\PlanPricingCache
 */
final readonly class PlanPricing
{
    /**
     * @param  int  $basePriceCents  Monthly base price in cents.
     * @param  int  $includedUnits  Included monthly allowance in units.
     * @param  int  $overageRateMicros  Overage rate in micro-dollars per unit (4 dp × 10_000).
     * @param  int  $cycleDays  Billing cycle length in days.
     */
    public function __construct(
        public int $basePriceCents,
        public int $includedUnits,
        public int $overageRateMicros,
        public int $cycleDays,
    ) {
        //
    }

    /**
     * @param  array{base_price_cents: int, included_units: int, overage_rate_micros: int, cycle_days: int}  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            basePriceCents: $data['base_price_cents'],
            includedUnits: $data['included_units'],
            overageRateMicros: $data['overage_rate_micros'],
            cycleDays: $data['cycle_days'],
        );
    }

    /**
     * @return array{base_price_cents: int, included_units: int, overage_rate_micros: int, cycle_days: int}
     */
    public function toArray(): array
    {
        return [
            'base_price_cents'    => $this->basePriceCents,
            'included_units'      => $this->includedUnits,
            'overage_rate_micros' => $this->overageRateMicros,
            'cycle_days'          => $this->cycleDays,
        ];
    }
}
