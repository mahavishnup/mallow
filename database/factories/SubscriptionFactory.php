<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SubscriptionStatus;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Subscription>
 */
final class SubscriptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'plan_id'     => Plan::factory(),
            'merchant_id' => function (array $attributes): int {
                $customerId = (int) $attributes['customer_id'];

                return Customer::query()->findOrFail($customerId)->merchant_id;
            },
            'starts_at' => Carbon::today(),
            'ends_at'   => null,
            'status'    => SubscriptionStatus::Active->value,
        ];
    }
}
