<?php

declare(strict_types=1);

use App\Models\ApiKey;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Team;

use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;

beforeEach(function (): void {
    $this->merchant = Team::factory()->create();
    $this->plainKey = 'mall_' . bin2hex(random_bytes(32));
    ApiKey::factory()->create([
        'merchant_id' => $this->merchant->id,
        'key_hash'    => hash('sha256', $this->plainKey),
    ]);

    $this->customer = Customer::factory()->create(['merchant_id' => $this->merchant->id]);
    $this->basicPlan = Plan::factory()->create([
        'merchant_id'    => $this->merchant->id,
        'base_price'     => '30.00',
        'included_units' => 1_000,
        'overage_rate'   => '0.0100',
    ]);
    $this->proPlan = Plan::factory()->create([
        'merchant_id'    => $this->merchant->id,
        'base_price'     => '60.00',
        'included_units' => 2_000,
        'overage_rate'   => '0.0200',
    ]);
});

/**
 * @param  array<string, mixed>  $payload
 * @return array<string, string>
 */
function merchantHeaders(?string $plainKey = null): array
{
    return ['X-Api-Key' => $plainKey ?? test()->plainKey];
}

test('plans can be listed and created via api', function (): void {
    getJson('/api/plans', merchantHeaders())
        ->assertOk()
        ->assertJsonCount(2, 'data');

    postJson('/api/plans', [
        'name'               => 'Enterprise',
        'base_price'         => 99.5,
        'included_units'     => 50_000,
        'overage_rate'       => 0.005,
        'billing_cycle_days' => 30,
    ], merchantHeaders())
        ->assertCreated()
        ->assertJsonPath('data.name', 'Enterprise')
        ->assertJsonPath('data.base_price', '99.50');

    expect(Plan::query()->where('merchant_id', $this->merchant->id)->count())->toBe(3);
});

test('customers can be listed via api', function (): void {
    Customer::factory()->count(2)->create(['merchant_id' => $this->merchant->id]);

    getJson('/api/customers', merchantHeaders())
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

test('subscriptions can be created via api with an initial segment', function (): void {
    postJson('/api/subscriptions', [
        'customer_id' => $this->customer->id,
        'plan_id'     => $this->basicPlan->id,
    ], merchantHeaders())
        ->assertCreated()
        ->assertJsonPath('data.status', 'active')
        ->assertJsonCount(1, 'data.segments');

    $segment = Subscription::query()->first()->segments()->sole();

    // Initial segment spans exactly one billing cycle.
    expect($segment->starts_at->toDateString())->toBe(today()->toDateString())
        ->and((int) $segment->starts_at->diffInDays($segment->ends_at))->toBe(30);
});

test('mid-cycle plan change closes and opens segments correctly', function (): void {
    $subscription = Subscription::factory()->create([
        'customer_id' => $this->customer->id,
        'merchant_id' => $this->merchant->id,
        'plan_id'     => $this->basicPlan->id,
        'starts_at'   => '2026-09-01',
    ]);

    $subscription->segments()->create([
        'plan_id'   => $this->basicPlan->id,
        'starts_at' => '2026-09-01',
        'ends_at'   => '2026-10-01',
    ]);

    patchJson("/api/subscriptions/{$subscription->id}/plan", [
        'plan_id'        => $this->proPlan->id,
        'effective_date' => '2026-09-16',
    ], merchantHeaders())
        ->assertOk()
        ->assertJsonPath('data.plan_id', $this->proPlan->id);

    $segments = $subscription->fresh()->segments()->orderBy('starts_at')->get();

    // Half-open [start, end): the old segment ends AT the effective date —
    // no gap day, no zero-day segment.
    expect($segments->count())->toBe(2)
        ->and($segments[0]->starts_at->toDateString())->toBe('2026-09-01')
        ->and($segments[0]->ends_at->toDateString())->toBe('2026-09-16')
        ->and($segments[0]->plan_id)->toBe($this->basicPlan->id)
        ->and($segments[1]->starts_at->toDateString())->toBe('2026-09-16')
        ->and($segments[1]->ends_at->toDateString())->toBe('2026-10-01')
        ->and($segments[1]->plan_id)->toBe($this->proPlan->id);
});

test('plan change effective on the cycle boundary produces a single segment', function (): void {
    $subscription = Subscription::factory()->create([
        'customer_id' => $this->customer->id,
        'merchant_id' => $this->merchant->id,
        'plan_id'     => $this->basicPlan->id,
        'starts_at'   => '2026-09-01',
    ]);

    $subscription->segments()->create([
        'plan_id'   => $this->basicPlan->id,
        'starts_at' => '2026-09-01',
        'ends_at'   => '2026-10-01',
    ]);

    // Effective exactly at the segment start → replace, not split (D3.4).
    patchJson("/api/subscriptions/{$subscription->id}/plan", [
        'plan_id'        => $this->proPlan->id,
        'effective_date' => '2026-09-01',
    ], merchantHeaders())->assertOk();

    $segments = $subscription->fresh()->segments()->get();

    expect($segments->count())->toBe(1)
        ->and($segments->sole()->plan_id)->toBe($this->proPlan->id)
        ->and($segments->sole()->ends_at->toDateString())->toBe('2026-10-01');
});

test('invoices can be generated via api', function (): void {
    $subscription = Subscription::factory()->create([
        'customer_id' => $this->customer->id,
        'merchant_id' => $this->merchant->id,
        'plan_id'     => $this->basicPlan->id,
        'starts_at'   => '2026-09-01',
    ]);

    $subscription->segments()->create([
        'plan_id'   => $this->basicPlan->id,
        'starts_at' => '2026-09-01',
        'ends_at'   => '2026-10-01',
    ]);

    postJson("/api/subscriptions/{$subscription->id}/invoice", [
        'period_start' => '2026-09-01',
        'period_end'   => '2026-10-01',
    ], merchantHeaders())
        ->assertOk()
        ->assertJsonPath('data.status', 'draft')
        ->assertJsonPath('data.total_amount', '30.00')
        ->assertJsonCount(1, 'data.items');
});

test('tenant isolation holds across all new endpoints', function (): void {
    $otherMerchant = Team::factory()->create();
    $otherKey = 'mall_' . bin2hex(random_bytes(32));
    ApiKey::factory()->create([
        'merchant_id' => $otherMerchant->id,
        'key_hash'    => hash('sha256', $otherKey),
    ]);

    // Plans list only the caller's merchant.
    getJson('/api/plans', merchantHeaders($otherKey))
        ->assertOk()
        ->assertJsonCount(0, 'data');

    // Cannot create a subscription for another merchant's customer/plan.
    postJson('/api/subscriptions', [
        'customer_id' => $this->customer->id,
        'plan_id'     => $this->basicPlan->id,
    ], merchantHeaders($otherKey))->assertNotFound();

    // Cannot touch another merchant's subscription.
    $subscription = Subscription::factory()->create([
        'customer_id' => $this->customer->id,
        'merchant_id' => $this->merchant->id,
        'plan_id'     => $this->basicPlan->id,
    ]);

    patchJson("/api/subscriptions/{$subscription->id}/plan", [
        'plan_id' => $this->proPlan->id,
    ], merchantHeaders($otherKey))->assertNotFound();

    postJson("/api/subscriptions/{$subscription->id}/invoice", [], merchantHeaders($otherKey))
        ->assertNotFound();

    expect($subscription->fresh()->plan_id)->toBe($this->basicPlan->id);
});
