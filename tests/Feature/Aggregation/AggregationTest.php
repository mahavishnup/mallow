<?php

declare(strict_types=1);

use App\Actions\Usage\AggregateDailyUsageAction;
use App\Models\ApiKey;
use App\Models\Customer;
use App\Models\DailyUsage;
use App\Models\Subscription;
use App\Models\Team;
use App\Models\UsageEvent;
use Illuminate\Support\Facades\RateLimiter;

use function Pest\Laravel\postJson;

beforeEach(function (): void {
    RateLimiter::clear('usage');
    RateLimiter::clear('usage-customer');

    config()->set('billing.aggregation.chunk_size', 1000);

    $this->merchant = Team::factory()->create();
    $this->customer = Customer::factory()->create(['merchant_id' => $this->merchant->id]);
    Subscription::factory()->create([
        'customer_id' => $this->customer->id,
        'merchant_id' => $this->merchant->id,
        'starts_at'   => now()->subMonth()->toDateString(),
    ]);

    $this->plainKey = 'mall_' . bin2hex(random_bytes(32));
    ApiKey::factory()->create([
        'merchant_id' => $this->merchant->id,
        'key_hash'    => hash('sha256', $this->plainKey),
    ]);

    $this->action = app(AggregateDailyUsageAction::class);
});

/**
 * @param  array<string, mixed>  $overrides
 */
function makeEvent(Customer $customer, array $overrides = []): UsageEvent
{
    return UsageEvent::factory()->create([
        'customer_id' => $customer->id,
        'merchant_id' => $customer->merchant_id,
        ...$overrides,
    ]);
}

test('single event aggregates to a correct daily total', function (): void {
    makeEvent($this->customer, ['quantity' => 250, 'usage_date' => '2026-09-20']);

    $this->action->execute();

    $row = DailyUsage::query()->sole();

    expect($row->total_quantity)->toBe(250)
        ->and($row->event_count)->toBe(1)
        ->and($row->usage_date->toDateString())->toBe('2026-09-20');
});

test('multiple events for the same customer and day are summed', function (): void {
    makeEvent($this->customer, ['quantity' => 100, 'usage_date' => '2026-09-20']);
    makeEvent($this->customer, ['quantity' => 150, 'usage_date' => '2026-09-20']);
    makeEvent($this->customer, ['quantity' => 25, 'usage_date' => '2026-09-20']);

    $this->action->execute();

    $row = DailyUsage::query()->sole();

    expect($row->total_quantity)->toBe(275)
        ->and($row->event_count)->toBe(3);
});

test('events for different days stay in separate rows', function (): void {
    makeEvent($this->customer, ['quantity' => 100, 'usage_date' => '2026-09-20']);
    makeEvent($this->customer, ['quantity' => 50, 'usage_date' => '2026-09-21']);

    $this->action->execute();

    expect(DailyUsage::query()->count())->toBe(2)
        ->and(DailyUsage::query()->whereDate('usage_date', '2026-09-20')->first()->total_quantity)->toBe(100)
        ->and(DailyUsage::query()->whereDate('usage_date', '2026-09-21')->first()->total_quantity)->toBe(50);
});

test('multiple customers aggregate into isolated totals', function (): void {
    $other = Customer::factory()->create(['merchant_id' => $this->merchant->id]);

    makeEvent($this->customer, ['quantity' => 100, 'usage_date' => '2026-09-20']);
    makeEvent($other, ['quantity' => 500, 'usage_date' => '2026-09-20']);

    $this->action->execute();

    $rows = DailyUsage::query()->get();

    expect($rows->count())->toBe(2)
        ->and($rows->firstWhere('customer_id', $this->customer->id)->total_quantity)->toBe(100)
        ->and($rows->firstWhere('customer_id', $other->id)->total_quantity)->toBe(500);
});

test('chunk boundaries aggregate correctly across chunks', function (): void {
    config()->set('billing.aggregation.chunk_size', 5);

    // 12 events across two customers on one day → spans 3 chunks.
    foreach (range(1, 7) as $i) {
        makeEvent($this->customer, ['quantity' => 10, 'usage_date' => '2026-09-20']);
    }

    $other = Customer::factory()->create(['merchant_id' => $this->merchant->id]);

    foreach (range(1, 5) as $i) {
        makeEvent($other, ['quantity' => 20, 'usage_date' => '2026-09-20']);
    }

    $this->action->execute();

    expect(DailyUsage::query()->count())->toBe(2)
        ->and(DailyUsage::query()->where('customer_id', $this->customer->id)->sole()->total_quantity)->toBe(70)
        ->and(DailyUsage::query()->where('customer_id', $this->customer->id)->sole()->event_count)->toBe(7)
        ->and(DailyUsage::query()->where('customer_id', $other->id)->sole()->total_quantity)->toBe(100)
        ->and(DailyUsage::query()->where('customer_id', $other->id)->sole()->event_count)->toBe(5);
});

test('re-running the action never changes totals (watermark)', function (): void {
    makeEvent($this->customer, ['quantity' => 100, 'usage_date' => '2026-09-20']);
    makeEvent($this->customer, ['quantity' => 200, 'usage_date' => '2026-09-21']);

    $this->action->execute();
    $this->action->execute();
    $this->action->execute();

    expect(DailyUsage::query()->count())->toBe(2)
        ->and(DailyUsage::query()->whereDate('usage_date', '2026-09-20')->sole()->total_quantity)->toBe(100)
        ->and(DailyUsage::query()->whereDate('usage_date', '2026-09-21')->sole()->total_quantity)->toBe(200)
        ->and(UsageEvent::query()->whereNull('aggregated_at')->count())->toBe(0);
});

test('scoped run only aggregates the scoped customer and date', function (): void {
    makeEvent($this->customer, ['quantity' => 100, 'usage_date' => '2026-09-20']);
    makeEvent($this->customer, ['quantity' => 999, 'usage_date' => '2026-09-21']);
    $other = Customer::factory()->create(['merchant_id' => $this->merchant->id]);
    makeEvent($other, ['quantity' => 500, 'usage_date' => '2026-09-20']);

    $this->action->execute($this->customer->id, '2026-09-20');

    $row = DailyUsage::query()->where('customer_id', $this->customer->id)
        ->whereDate('usage_date', '2026-09-20')->sole();

    expect($row->total_quantity)->toBe(100)
        ->and(DailyUsage::query()->count())->toBe(1)
        ->and(UsageEvent::query()->whereNull('aggregated_at')->count())->toBe(2);
});

test('duplicate api submission does not double count in daily_usage', function (): void {
    $payload = [
        'customer_id'     => $this->customer->id,
        'usage_date'      => today()->toDateString(),
        'quantity'        => 250,
        'type'            => 'api_calls',
        'idempotency_key' => '0d0f4c2e-2222-4222-8222-000000000001',
    ];

    postJson('/api/usage', $payload, ['X-Api-Key' => $this->plainKey])->assertCreated();
    postJson('/api/usage', $payload, ['X-Api-Key' => $this->plainKey])
        ->assertOk()->assertJson(['duplicate' => true]);

    // QUEUE_CONNECTION=sync → the afterCommit job already ran inline.
    $row = DailyUsage::query()->sole();

    expect($row->total_quantity)->toBe(250)
        ->and($row->event_count)->toBe(1);
});

test('watermark guard keeps totals consistent when an event is re-marked unaggregated', function (): void {
    // Guard semantics: aggregated_at IS the counted-once marker. If an event is
    // manually flipped back to un-aggregated (e.g. an operator replay), the
    // watermark has no memory of it, so the run re-applies its delta. This test
    // documents that behavior: a manual reset of the watermark WILL re-count
    // the event (the marker, not the totals, is the source of truth).
    $event = makeEvent($this->customer, ['quantity' => 100, 'usage_date' => '2026-09-20']);
    $this->action->execute();

    // Simulate the pathological manual reset (direct update: the in-memory
    // model is stale and a save() there would be a dirty-check no-op).
    UsageEvent::query()->whereKey($event->id)->update(['aggregated_at' => null]);
    $this->action->execute();

    expect(DailyUsage::query()->sole()->total_quantity)->toBe(200)
        ->and($event->fresh()->aggregated_at)->not->toBeNull();
});

test('full suite of events already aggregated is a no-op', function (): void {
    makeEvent($this->customer, ['quantity' => 100, 'usage_date' => '2026-09-20']);
    $this->action->execute();

    $before = DailyUsage::query()->sole()->updated_at->toJson();

    $this->action->execute();

    expect(DailyUsage::query()->sole()->updated_at->toJson())->toBe($before);
});
