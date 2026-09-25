<?php

declare(strict_types=1);

use App\Models\ApiKey;
use App\Models\Customer;
use App\Models\DailyUsage;
use App\Models\Subscription;
use App\Models\Team;
use App\Models\UsageEvent;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\Fluent\AssertableJson;
use Illuminate\Testing\TestResponse;

use function Pest\Laravel\postJson;

beforeEach(function (): void {
    RateLimiter::clear('usage');
    RateLimiter::clear('usage-customer');

    config()->set('billing.ingestion.merchant_per_minute', 600);
    config()->set('billing.ingestion.customer_per_minute', 60);

    $this->merchant = Team::factory()->create(['name' => 'Merchant A']);

    // Mint a key with a known plaintext so tests can authenticate.
    $this->plainKey = 'mall_' . bin2hex(random_bytes(32));
    ApiKey::factory()->create([
        'merchant_id' => $this->merchant->id,
        'key_hash'    => hash('sha256', $this->plainKey),
    ]);

    $this->customer = Customer::factory()->create(['merchant_id' => $this->merchant->id]);
    Subscription::factory()->create([
        'customer_id' => $this->customer->id,
        'merchant_id' => $this->merchant->id,
        'starts_at'   => now()->subDays(10)->toDateString(),
        'ends_at'     => null,
    ]);

    $this->payload = [
        'customer_id'     => $this->customer->id,
        'usage_date'      => today()->toDateString(),
        'quantity'        => 250,
        'type'            => 'api_calls',
        'idempotency_key' => '0d0f4c2e-1111-4111-8111-000000000001',
    ];
});

/**
 * POST /api/usage with merchant authentication.
 *
 * @param  array<string, mixed>  $payload
 */
function recordUsage(array $payload, ?string $plainKey = null): TestResponse
{
    return postJson('/api/usage', $payload, [
        'X-Api-Key' => $plainKey ?? test()->plainKey,
    ]);
}

test('valid request stores a usage event and aggregates it via the queued job', function (): void {
    recordUsage($this->payload)
        ->assertCreated()
        ->assertJson(fn (AssertableJson $json) => $json
            ->where('data.customer_id', $this->customer->id)
            ->where('data.quantity', 250)
            ->etc());

    // QUEUE_CONNECTION=sync in tests: the afterCommit aggregation job has
    // already run, so the watermark advanced and daily_usage is populated.
    expect(UsageEvent::query()->count())->toBe(1)
        ->and(UsageEvent::query()->first()->aggregated_at)->not->toBeNull()
        ->and(DailyUsage::query()->where('customer_id', $this->customer->id)->sole()->total_quantity)->toBe(250);
});

test('identical retry returns duplicate without a second row', function (): void {
    recordUsage($this->payload)->assertCreated();

    recordUsage($this->payload)
        ->assertOk()
        ->assertJson(['duplicate' => true]);

    expect(UsageEvent::query()->count())->toBe(1);
});

test('db constraint catches a duplicate insert that raced past the fast path', function (): void {
    // Direct row insert simulating a request that passed the exists() fast
    // path before another process committed the same idempotency key.
    UsageEvent::factory()->create([
        'uuid'        => $this->payload['idempotency_key'],
        'customer_id' => $this->customer->id,
        'merchant_id' => $this->merchant->id,
    ]);

    recordUsage($this->payload)
        ->assertOk()
        ->assertJson(['duplicate' => true]);

    expect(UsageEvent::query()->count())->toBe(1);
});

test('missing api key is rejected with 401', function (): void {
    postJson('/api/usage', $this->payload)->assertUnauthorized();
});

test('invalid api key is rejected with 401', function (): void {
    postJson('/api/usage', $this->payload, ['X-Api-Key' => 'mall_not_a_real_key'])->assertUnauthorized();
});

test('validation failures return 422', function (array $overrides): void {
    recordUsage([...$this->payload, ...$overrides])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(array_keys($overrides));

    expect(UsageEvent::query()->count())->toBe(0);
})->with([
    'zero quantity'            => [['quantity' => 0]],
    'negative quantity'        => [['quantity' => -5]],
    'string quantity'          => [['quantity' => 'abc']],
    'missing idempotency key'  => [['idempotency_key' => null]],
    'non-uuid idempotency key' => [['idempotency_key' => 'not-a-uuid']],
    'unknown customer'         => [['customer_id' => 999999]],
]);

test('future usage dates are rejected with 422', function (): void {
    recordUsage([...$this->payload, 'usage_date' => today()->addDay()->toDateString()])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('usage_date');
});

test('merchant cannot record usage for another merchant customer', function (): void {
    $otherCustomer = Customer::factory()->create();

    recordUsage([...$this->payload, 'customer_id' => $otherCustomer->id])
        ->assertNotFound();

    expect(UsageEvent::query()->count())->toBe(0);
});

test('customer without an active subscription is rejected', function (): void {
    $bare = Customer::factory()->create(['merchant_id' => $this->merchant->id]);

    recordUsage([...$this->payload, 'customer_id' => $bare->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('customer_id');

    expect(UsageEvent::query()->count())->toBe(0);
});

test('merchant rate limit returns 429 when exceeded', function (): void {
    config()->set('billing.ingestion.merchant_per_minute', 2);

    recordUsage($this->payload)->assertCreated();
    recordUsage([...$this->payload, 'idempotency_key' => '0d0f4c2e-1111-4111-8111-000000000002'])->assertCreated();

    recordUsage([...$this->payload, 'idempotency_key' => '0d0f4c2e-1111-4111-8111-000000000003'])
        ->assertStatus(429)
        ->assertHeader('Retry-After');
});

test('customer rate limit is independent of the merchant limit', function (): void {
    config()->set('billing.ingestion.customer_per_minute', 2);

    $secondCustomer = Customer::factory()->create(['merchant_id' => $this->merchant->id]);
    Subscription::factory()->create([
        'customer_id' => $secondCustomer->id,
        'merchant_id' => $this->merchant->id,
    ]);

    recordUsage($this->payload)->assertCreated();
    recordUsage([...$this->payload, 'idempotency_key' => '0d0f4c2e-1111-4111-8111-000000000002'])->assertCreated();

    // Third call for the SAME customer trips the per-customer bucket…
    recordUsage([...$this->payload, 'idempotency_key' => '0d0f4c2e-1111-4111-8111-000000000003'])
        ->assertStatus(429);

    // …but a different customer under the same merchant is unaffected.
    recordUsage([
        ...$this->payload,
        'customer_id'     => $secondCustomer->id,
        'idempotency_key' => '0d0f4c2e-1111-4111-8111-000000000004',
    ])->assertCreated();
});
