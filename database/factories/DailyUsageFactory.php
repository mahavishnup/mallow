<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Customer;
use App\Models\DailyUsage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<DailyUsage>
 */
final class DailyUsageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'merchant_id'    => fn (array $attributes): int => Customer::query()->findOrFail((int) $attributes['customer_id'])->merchant_id,
            'customer_id'    => Customer::factory(),
            'usage_date'     => today(),
            'total_quantity' => fake()->numberBetween(100, 50_000),
            'event_count'    => 1,
        ];
    }

    /**
     * Explicit aggregate values for a customer/date pair.
     */
    public function forDay(int $customerId, string $usageDate, int $totalQuantity, int $eventCount): static
    {
        return $this->state(fn (array $attributes): array => [
            'customer_id'    => $customerId,
            'usage_date'     => Carbon::parse($usageDate)->toDateString(),
            'total_quantity' => $totalQuantity,
            'event_count'    => $eventCount,
        ]);
    }
}
