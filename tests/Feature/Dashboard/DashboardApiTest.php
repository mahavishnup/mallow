<?php

declare(strict_types=1);

use App\Data\PlanPricing;
use App\Models\ApiKey;
use App\Models\Customer;
use App\Models\DailyUsage;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Team;
use App\Services\PlanPricingCache;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

use function Pest\Laravel\getJson;

beforeEach(function (): void {
    Cache::flush();

    $this->merchant = Team::factory()->create(['name' => 'Dash Merchant']);
    $this->plainKey = 'mall_' . bin2hex(random_bytes(32));
    ApiKey::factory()->create([
        'merchant_id' => $this->merchant->id,
        'key_hash'    => hash('sha256', $this->plainKey),
    ]);

    $this->plan = Plan::factory()->create([
        'merchant_id'        => $this->merchant->id,
        'base_price'         => '30.00',
        'included_units'     => 1_000,
        'overage_rate'       => '0.0100',
        'billing_cycle_days' => 30,
    ]);
});

/**
 * Seed one daily_usage row.
 */
function dashUsage(int $customerId, int $merchantId, string $date, int $quantity): void
{
    DailyUsage::factory()->create([
        'merchant_id'    => $merchantId,
        'customer_id'    => $customerId,
        'usage_date'     => $date,
        'total_quantity' => $quantity,
        'event_count'    => 1,
    ]);
}

test('top five customers are ordered by current month usage', function (): void {
    foreach (range(1, 6) as $i) {
        $customer = Customer::factory()->create(['merchant_id' => $this->merchant->id, 'name' => "Customer {$i}"]);
        Subscription::factory()->create([
            'customer_id' => $customer->id,
            'merchant_id' => $this->merchant->id,
            'plan_id'     => $this->plan->id,
        ]);
        dashUsage($customer->id, $this->merchant->id, now()->toDateString(), $i * 100);
    }

    $response = getJson("/api/merchants/{$this->merchant->id}/dashboard", ['X-Api-Key' => $this->plainKey]);

    $response->assertOk();

    $top = $response->json('data.top_customers');

    expect(count($top))->toBe(5)
        ->and($top[0]['name'])->toBe('Customer 6')
        ->and($top[0]['total_quantity'])->toBe(600)
        ->and($top[4]['name'])->toBe('Customer 2');
});

test('projected overage uses linear projection with the day one guard', function (): void {
    $customer = Customer::factory()->create(['merchant_id' => $this->merchant->id, 'name' => 'Heavy']);

    // Cycle started today → days_elapsed clamps to 1 (day-one guard).
    Subscription::factory()->create([
        'customer_id' => $customer->id,
        'merchant_id' => $this->merchant->id,
        'plan_id'     => $this->plan->id,
        'starts_at'   => today()->toDateString(),
    ]);

    dashUsage($customer->id, $this->merchant->id, today()->toDateString(), 200);

    $response = getJson("/api/merchants/{$this->merchant->id}/dashboard", ['X-Api-Key' => $this->plainKey]);
    $response->assertOk();

    $row = $response->json('data.projected_overage.subscriptions.0');

    // Day-one guard: projected = usage × cycle_days / max(1, elapsed) = 200 × 30.
    expect($row['usage_to_date'])->toBe(200)
        ->and($row['projected_usage'])->toBe(6_000)
        ->and($row['projected_overage_units'])->toBe(5_000)    // 6000 − 1000
        ->and($row['projected_overage_cents'])->toBe(5_000);   // 5000 × $0.01
});

test('projected overage projects linearly for elapsed cycles', function (): void {
    $customer = Customer::factory()->create(['merchant_id' => $this->merchant->id, 'name' => 'Steady']);

    // Cycle started 10 days ago → 10 days elapsed of a 30-day cycle.
    Subscription::factory()->create([
        'customer_id' => $customer->id,
        'merchant_id' => $this->merchant->id,
        'plan_id'     => $this->plan->id,
        'starts_at'   => today()->subDays(10)->toDateString(),
    ]);

    dashUsage($customer->id, $this->merchant->id, today()->toDateString(), 800);

    $response = getJson("/api/merchants/{$this->merchant->id}/dashboard", ['X-Api-Key' => $this->plainKey]);

    $row = $response->json('data.projected_overage.subscriptions.0');

    // 800 / 10 × 30 = 2,400 projected; overage 1,400 × $0.01 = $14.00.
    expect($row['projected_usage'])->toBe(2_400)
        ->and($row['projected_overage_units'])->toBe(1_400)
        ->and($row['projected_overage_cents'])->toBe(1_400)
        ->and($response->json('data.projected_overage.total_cents'))->toBe(1_400);
});

test('churn risk flags more than fifty percent drops and excludes zero previous month', function (): void {
    $churned = Customer::factory()->create(['merchant_id' => $this->merchant->id, 'name' => 'Churned']);
    $stable = Customer::factory()->create(['merchant_id' => $this->merchant->id, 'name' => 'Stable']);
    $brandNew = Customer::factory()->create(['merchant_id' => $this->merchant->id, 'name' => 'New']);

    // Churned: 10,000 → 2,000 = 80% drop → flagged.
    dashUsage($churned->id, $this->merchant->id, now()->subMonth()->startOfMonth()->addDays(5)->toDateString(), 10_000);
    dashUsage($churned->id, $this->merchant->id, now()->toDateString(), 2_000);

    // Stable: 5,000 → 4,000 = 20% drop → not flagged.
    dashUsage($stable->id, $this->merchant->id, now()->subMonth()->startOfMonth()->addDays(5)->toDateString(), 5_000);
    dashUsage($stable->id, $this->merchant->id, now()->toDateString(), 4_000);

    // Brand new: previous month 0 → excluded by convention.
    dashUsage($brandNew->id, $this->merchant->id, now()->toDateString(), 100);

    $response = getJson("/api/merchants/{$this->merchant->id}/dashboard", ['X-Api-Key' => $this->plainKey]);

    $churn = $response->json('data.churn_risk');

    expect(count($churn))->toBe(1)
        ->and($churn[0]['name'])->toBe('Churned')
        ->and($churn[0]['previous_month'])->toBe(10_000)
        ->and($churn[0]['current_month'])->toBe(2_000)
        ->and((float) $churn[0]['drop_percentage'])->toBe(80.0);
});

test('current month zero usage with history is flagged as one hundred percent drop', function (): void {
    $gone = Customer::factory()->create(['merchant_id' => $this->merchant->id, 'name' => 'Gone']);

    dashUsage($gone->id, $this->merchant->id, now()->subMonth()->startOfMonth()->addDays(5)->toDateString(), 9_000);

    $response = getJson("/api/merchants/{$this->merchant->id}/dashboard", ['X-Api-Key' => $this->plainKey]);

    $churn = $response->json('data.churn_risk');

    expect(count($churn))->toBe(1)
        ->and((float) $churn[0]['drop_percentage'])->toBe(100.0);
});

test('tenant isolation keeps other merchants data out', function (): void {
    $other = Team::factory()->create();
    $otherCustomer = Customer::factory()->create(['merchant_id' => $other->id]);
    Subscription::factory()->create([
        'customer_id' => $otherCustomer->id,
        'merchant_id' => $other->id,
        'plan_id'     => Plan::factory()->create(['merchant_id' => $other->id])->id,
    ]);
    dashUsage($otherCustomer->id, $other->id, now()->toDateString(), 99_999);

    // Wrong {id} for this key → 403.
    getJson("/api/merchants/{$other->id}/dashboard", ['X-Api-Key' => $this->plainKey])->assertForbidden();

    // Own dashboard contains none of the other merchant's numbers.
    $response = getJson("/api/merchants/{$this->merchant->id}/dashboard", ['X-Api-Key' => $this->plainKey]);

    $response->assertOk();
    expect($response->json('data.top_customers'))->toBe([]);
});

test('plan mutation invalidates the pricing cache', function (): void {
    $cache = app(PlanPricingCache::class);

    // Prime the cache.
    $pricing = $cache->get($this->plan);
    expect($pricing->basePriceCents)->toBe(3_000);

    // Mutate the price (model event must invalidate).
    $this->plan->update(['base_price' => '50.00']);

    $fresh = $cache->get($this->plan->refresh());
    expect($fresh->basePriceCents)->toBe(5_000)
        ->and($fresh->basePriceCents)->not->toBe($pricing->basePriceCents);
});

test('pricing cache hit avoids the database on second read', function (): void {
    $cache = app(PlanPricingCache::class);

    $plan = $this->plan->refresh(); // Hydrate before measuring queries.
    $cache->get($plan); // Prime.

    DB::enableQueryLog();

    $cached = $cache->get($plan);

    expect(DB::getQueryLog())->toBeEmpty()
        ->and($cached)->toBeInstanceOf(PlanPricing::class)
        ->and($cached->basePriceCents)->toBe(3_000);

    DB::disableQueryLog();
});

test('session authenticated team member can access the dashboard without an api key', function (): void {
    $user = App\Models\User::factory()->create();
    $user->teams()->attach($this->merchant, ['role' => App\Enums\TeamRole::Member->value]);

    $response = $this->actingAs($user)
        ->get("/api/merchants/{$this->merchant->id}/dashboard");

    $response->assertOk()
        ->assertJsonPath('data.merchant.id', $this->merchant->id);

    // Non-members are rejected.
    $outsider = App\Models\User::factory()->create();
    $this->actingAs($outsider)
        ->get("/api/merchants/{$this->merchant->id}/dashboard")
        ->assertForbidden();
});

test('dashboard requires authentication when no api key resolves', function (): void {
    getJson("/api/merchants/{$this->merchant->id}/dashboard")->assertUnauthorized();
});

test('dashboard exposes cycle overview, active plan, and zero filled daily trend', function (): void {
    $customer = Customer::factory()->create(['merchant_id' => $this->merchant->id, 'name' => 'Trendy']);

    Subscription::factory()->create([
        'customer_id' => $customer->id,
        'merchant_id' => $this->merchant->id,
        'plan_id'     => $this->plan->id,
        'starts_at'   => now()->utc()->subDays(3)->toDateString(),
    ]);

    // Usage on the UTC grid — the trend window is anchored to now()->utc(),
    // so test rows must use the same clock or indices drift across timezones.
    dashUsage($customer->id, $this->merchant->id, now()->utc()->toDateString(), 300);
    dashUsage($customer->id, $this->merchant->id, now()->utc()->subDays(3)->toDateString(), 100);

    $response = getJson("/api/merchants/{$this->merchant->id}/dashboard", ['X-Api-Key' => $this->plainKey]);
    $response->assertOk();

    $cycle = $response->json('data.cycle_overview');
    expect($cycle['usage_to_date'])->toBe(400)
        ->and($cycle['included_units'])->toBe(1_000)
        ->and((float) $cycle['usage_percentage'])->toBe(40.0);

    $plan = $response->json('data.active_plan');
    expect($plan['name'])->toBe($this->plan->name)
        ->and($plan['billing_cycle_days'])->toBe(30);

    $trend = $response->json('data.daily_trend');
    $winStart = now()->utc()->subDays(29)->startOfDay()->toDateString();
    $winEnd = now()->utc()->startOfDay()->toDateString();

    expect(count($trend))->toBe(30)
        // today−3 sits at index 26 of a 30-day window (index 0 = today−29).
        ->and($trend[26]['total_quantity'])->toBe(100)
        ->and($trend[26]['date'])->toBe(now()->utc()->subDays(3)->startOfDay()->toDateString())
        ->and($trend[29]['total_quantity'])->toBe(300)
        ->and($trend[29]['date'])->toBe($winEnd)
        ->and($trend[0]['date'])->toBe($winStart)
        // Gap days are zero-filled, not missing.
        ->and($trend[0]['total_quantity'])->toBe(0)
        ->and($trend[15]['total_quantity'])->toBe(0);
});

test('active plan is null with no active subscriptions', function (): void {
    $response = getJson("/api/merchants/{$this->merchant->id}/dashboard", ['X-Api-Key' => $this->plainKey]);

    $response->assertOk();

    expect($response->json('data.active_plan'))->toBeNull()
        ->and($response->json('data.cycle_overview.usage_to_date'))->toBe(0);
});
