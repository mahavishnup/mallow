<?php

declare(strict_types=1);

use App\Services\ProrationService;

beforeEach(function (): void {
    $this->proration = new ProrationService;
});

test('full cycle applies no proration', function (): void {
    expect($this->proration->proratedBaseCents(2_900, 30, 30))->toBe(2_900)
        ->and($this->proration->proratedIncludedUnits(100_000, 30, 30))->toBe(100_000);
});

test('half cycle halves base and allowance with clean numbers', function (): void {
    expect($this->proration->proratedBaseCents(3_000, 15, 30))->toBe(1_500)
        ->and($this->proration->proratedIncludedUnits(10_000, 15, 30))->toBe(5_000);
});

test('base proration rounds half-up to the cent', function (): void {
    // 999 cents × 7/30 = 233.1 → 233
    expect($this->proration->proratedBaseCents(999, 7, 30))->toBe(233);

    // 999 cents × 15/30 = 499.5 → 500 (half rounds up)
    expect($this->proration->proratedBaseCents(999, 15, 30))->toBe(500);

    // 101 cents × 10/30 = 33.66… → 34
    expect($this->proration->proratedBaseCents(101, 10, 30))->toBe(34);
});

test('allowance proration rounds half-up to whole units', function (): void {
    // 1001 units × 15/30 = 500.5 → 501
    expect($this->proration->proratedIncludedUnits(1_001, 15, 30))->toBe(501);

    // 7 units × 1/3 = 2.33 → 2
    expect($this->proration->proratedIncludedUnits(7, 1, 3))->toBe(2);

    // 5 units × 1/2 = 2.5 → 3
    expect($this->proration->proratedIncludedUnits(5, 1, 2))->toBe(3);
});

test('overage cents multiply units by rate micros with half-up rounding', function (): void {
    $proration = $this->proration;

    // 0.0025 $/unit → 25 micros. 10,000 units × 0.0025 $ = $25.00 → 2500 cents.
    expect($proration->rateToMicros('0.0025'))->toBe(25)
        ->and($proration->overageCents(10_000, 25))->toBe(2_500);

    // 1 unit at 1 micro (0.0001 $) = 0.01 cents → rounds to 0
    expect($proration->rateToMicros('0.0001'))->toBe(1)
        ->and($proration->overageCents(1, 1))->toBe(0);

    // 150 units at 1 micro = 1.5 cents → half-up → 2
    expect($proration->overageCents(150, 1))->toBe(2);

    // rate 0.1234 $/unit → 1234 micros; 50,000 units = $6,170 → 617,000 cents
    expect($proration->rateToMicros('0.1234'))->toBe(1_234)
        ->and($proration->overageCents(50_000, 1_234))->toBe(617_000);
});

test('rate conversion covers boundary decimal rates', function (): void {
    expect($this->proration->rateToMicros('0.0000'))->toBe(0)
        ->and($this->proration->rateToMicros('1.0000'))->toBe(10_000)
        ->and($this->proration->rateToMicros('9999.9999'))->toBe(99_999_999);
});
