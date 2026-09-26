<?php

declare(strict_types=1);

use App\Enums\TeamRole;
use App\Models\Customer;
use App\Models\DailyUsage;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Team;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->team = Team::factory()->create(['name' => 'Billing Merchant']);
    $this->user->teams()->attach($this->team, ['role' => TeamRole::Owner->value]);
    $this->user->switchTeam($this->team);

    $this->plan = Plan::factory()->create([
        'merchant_id'        => $this->team->id,
        'base_price'         => '30.00',
        'included_units'     => 1_000,
        'overage_rate'       => '0.0100',
        'billing_cycle_days' => 30,
    ]);
});

test('team member sees the three billing metric props with fixture values', function (): void {
    $heavy = Customer::factory()->create(['merchant_id' => $this->team->id, 'name' => 'Heavy User']);
    $light = Customer::factory()->create(['merchant_id' => $this->team->id, 'name' => 'Light User']);

    Subscription::factory()->create([
        'customer_id' => $heavy->id,
        'merchant_id' => $this->team->id,
        'plan_id'     => $this->plan->id,
        'starts_at'   => today()->toDateString(),
    ]);

    DailyUsage::factory()->create([
        'merchant_id'    => $this->team->id,
        'customer_id'    => $heavy->id,
        'usage_date'     => today()->toDateString(),
        'total_quantity' => 500,
        'event_count'    => 1,
    ]);

    // Previous-month heavy usage for the light user → churn risk (>50% drop).
    DailyUsage::factory()->create([
        'merchant_id'    => $this->team->id,
        'customer_id'    => $light->id,
        'usage_date'     => now()->subMonth()->startOfMonth()->addDays(5)->toDateString(),
        'total_quantity' => 10_000,
        'event_count'    => 1,
    ]);
    DailyUsage::factory()->create([
        'merchant_id'    => $this->team->id,
        'customer_id'    => $light->id,
        'usage_date'     => today()->toDateString(),
        'total_quantity' => 1_000,
        'event_count'    => 1,
    ]);

    $response = $this->actingAs($this->user)->get(route('dashboard'));

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page
            ->component('dashboard')
            ->where('billingMonth', now()->utc()->format('Y-m'))
            ->has('usageTopCustomers', 2)
            ->where('usageTopCustomers.0.name', 'Light User') // 1,000 current month
            ->where('usageTopCustomers.1.name', 'Heavy User') // 500 current month
            ->has('projectedOverage.subscriptions', 1)
            ->where('projectedOverage.subscriptions.0.customer_name', 'Heavy User')
            ->has('churnRiskCustomers', 1)
            ->where('churnRiskCustomers.0.name', 'Light User')
            ->where('churnRiskCustomers.0.drop_percentage', fn ($value) => (float) $value === 90.0)
            ->has('cycleOverview')
            ->where('cycleOverview.usage_to_date', 1_500)
            ->where('cycleOverview.included_units', 1_000)
            ->where('activePlan.name', $this->plan->name)
            ->has('dailyTrend', 30)
    );
});

test('dashboard with no team context renders empty metric props', function (): void {
    $teamless = User::factory()->create();

    $response = $this->actingAs($teamless)->get(route('dashboard'));

    $response->assertOk()->assertInertia(
        fn (Assert $page) => $page
            ->component('dashboard')
            ->where('usageTopCustomers', [])
            ->where('churnRiskCustomers', [])
            ->where('projectedOverage.total_cents', 0)
    );
});
