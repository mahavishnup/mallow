<?php

declare(strict_types=1);

use App\Models\ApiKey;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionSegment;

test('plan factory creates a standalone plan with expected defaults', function () {
    $plan = Plan::factory()->create();

    expect($plan->exists)->toBeTrue()
        ->and($plan->base_price)->toBe('29.00')
        ->and($plan->overage_rate)->toBe('0.0025')
        ->and($plan->included_units)->toBe(100_000)
        ->and($plan->billing_cycle_days)->toBe(30)
        ->and($plan->is_active)->toBeTrue();
});

test('plan factory states override pricing and active flag', function () {
    $plan = Plan::factory()->withPricing(99.5, 5_000, 0.01)->inactive()->create();

    expect($plan->base_price)->toBe('99.50')
        ->and($plan->overage_rate)->toBe('0.0100')
        ->and($plan->included_units)->toBe(5_000)
        ->and($plan->is_active)->toBeFalse();
});

test('customer factory creates a standalone customer', function () {
    $customer = Customer::factory()->create();

    expect($customer->exists)->toBeTrue()
        ->and($customer->merchant)->not->toBeNull();
});

test('subscription factory creates a standalone subscription with denormalized merchant', function () {
    $subscription = Subscription::factory()->create();

    expect($subscription->exists)->toBeTrue()
        ->and($subscription->status)->toBe(App\Enums\SubscriptionStatus::Active)
        ->and($subscription->merchant_id)->toBe($subscription->customer->merchant_id);
});

test('subscription segment factory creates a standalone segment', function () {
    $segment = SubscriptionSegment::factory()->create();

    expect($segment->exists)->toBeTrue()
        ->and($segment->starts_at->lessThan($segment->ends_at))->toBeTrue();
});

test('api key factory creates a standalone key with hashed secret hidden from serialization', function () {
    $apiKey = ApiKey::factory()->create();

    expect($apiKey->exists)->toBeTrue()
        ->and(mb_strlen($apiKey->getRawOriginal('key_hash')))->toBe(64)
        ->and($apiKey->toArray())->not->toHaveKey('key_hash');
});
