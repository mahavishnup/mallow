<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Customer;
use App\Models\UsageEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<UsageEvent>
 */
final class UsageEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid'          => Str::uuid()->toString(),
            'merchant_id'   => fn (array $attributes): int => Customer::query()->findOrFail((int) $attributes['customer_id'])->merchant_id,
            'customer_id'   => Customer::factory(),
            'usage_date'    => today(),
            'quantity'      => fake()->numberBetween(1, 10_000),
            'type'          => 'api_calls',
            'aggregated_at' => null,
        ];
    }
}
