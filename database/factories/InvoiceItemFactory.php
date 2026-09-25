<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceItem>
 */
final class InvoiceItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'invoice_id'     => Invoice::factory(),
            'plan_id'        => Plan::factory(),
            'segment_start'  => today()->startOfMonth()->toDateString(),
            'segment_end'    => today()->addMonth()->startOfMonth()->toDateString(),
            'prorated_base'  => '29.00',
            'included_units' => 100_000,
            'billable_usage' => 0,
            'overage_units'  => 0,
            'overage_amount' => '0.00',
            'line_total'     => '29.00',
        ];
    }
}
