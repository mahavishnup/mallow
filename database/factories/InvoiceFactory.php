<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\InvoiceStatus;
use App\Models\Invoice;
use App\Models\Subscription;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
final class InvoiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subscription = Subscription::factory()->create();

        return [
            'subscription_id' => $subscription->id,
            'merchant_id'     => $subscription->merchant_id,
            'customer_id'     => $subscription->customer_id,
            'period_start'    => today()->startOfMonth()->toDateString(),
            'period_end'      => today()->addMonth()->startOfMonth()->toDateString(),
            'total_amount'    => '0.00',
            'status'          => InvoiceStatus::Draft->value,
        ];
    }
}
