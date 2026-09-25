<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Plan;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
final class PlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'merchant_id'        => Team::factory(),
            'name'               => 'Starter',
            'base_price'         => '29.00',
            'included_units'     => 100_000,
            'overage_rate'       => '0.0025',
            'billing_cycle_days' => 30,
            'is_active'          => true,
        ];
    }

    /**
     * Indicate that the plan is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate explicit pricing for the plan.
     */
    public function withPricing(float $base, int $units, float $rate): static
    {
        return $this->state(fn (array $attributes) => [
            'base_price'     => number_format($base, 2, '.', ''),
            'included_units' => $units,
            'overage_rate'   => number_format($rate, 4, '.', ''),
        ]);
    }
}
