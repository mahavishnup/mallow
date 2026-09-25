<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionSegment;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<SubscriptionSegment>
 */
final class SubscriptionSegmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = Carbon::today();

        return [
            'subscription_id' => Subscription::factory(),
            'plan_id'         => Plan::factory(),
            'starts_at'       => $startsAt,
            'ends_at'         => $startsAt->copy()->addDays(30),
        ];
    }
}
