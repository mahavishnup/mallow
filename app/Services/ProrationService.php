<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;

/**
 * Day-based proration and overage math (Plan §4.2), computed entirely in
 * integers: cents for money, micro-units for the 4-dp overage rate.
 *
 * All division uses half-up rounding (D3.1/D3.3), matching decimal-column
 * storage and keeping totals deterministic across drivers.
 */
final class ProrationService
{
    /**
     * Scale factor between the decimal(10,4) overage_rate column and integer
     * micro-units: 0.0025 → 25 micros per unit.
     */
    public const int OVERAGE_RATE_SCALE = 10_000;

    /**
     * Cents-per-micro-dollar divisor: overage cents = units × micros / 100.
     */
    private const int CENTS_PER_RATE_MICRO = 100;

    /**
     * Prorated base price in cents: base × active_days / cycle_days, half-up.
     */
    public function proratedBaseCents(int $baseCents, int $activeDays, int $cycleDays): int
    {
        return $this->roundHalfUp($baseCents * $activeDays, $cycleDays);
    }

    /**
     * Prorated included allowance in whole units, half-up (D3.3).
     */
    public function proratedIncludedUnits(int $includedUnits, int $activeDays, int $cycleDays): int
    {
        return $this->roundHalfUp($includedUnits * $activeDays, $cycleDays);
    }

    /**
     * Overage charge in cents: overage units × rate micros, half-up.
     *
     * $rateMicros = round(overage_rate_decimal × 10_000) — see self::rateToMicros().
     * Micros are micro-dollars per unit, so cents = units × micros × 100 / 10_000
     * = units × micros / 100.
     */
    public function overageCents(int $overageUnits, int $rateMicros): int
    {
        return $this->roundHalfUp($overageUnits * $rateMicros, self::CENTS_PER_RATE_MICRO);
    }

    /**
     * Convert a decimal overage rate (e.g. "0.0025") to integer micro-units.
     */
    public function rateToMicros(string $decimalRate): int
    {
        return (int) round(((float) $decimalRate) * self::OVERAGE_RATE_SCALE);
    }

    /**
     * Half-up integer division of $numerator by $denominator.
     *
     * Both operands stay integers, so no float error can creep in. Negative
     * numerators round half-up toward positive infinity, matching PHP's
     * intdiv semantics for the sign and the "0.5 goes up" convention.
     */
    private function roundHalfUp(int $numerator, int $denominator): int
    {
        if ($denominator <= 0) {
            throw new InvalidArgumentException('Denominator must be positive.');
        }

        $quotient = intdiv($numerator, $denominator);
        $remainder = $numerator % $denominator;

        if ($remainder * 2 >= $denominator) {
            $quotient += 1;
        }

        return $quotient;
    }
}
