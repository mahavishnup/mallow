<?php

declare(strict_types=1);

use App\Models\ApiKey;
use App\Models\Team;

use function Pest\Laravel\artisan;

test('billing:api-key creates a key for an existing merchant', function (): void {
    $team = Team::factory()->create(['name' => 'Test Merchant']);

    artisan('billing:api-key', ['--merchant' => $team->id, '--name' => 'CI key'])
        ->assertSuccessful()
        ->expectsOutputToContain('mall_')
        ->expectsOutputToContain('Store this key now');

    $key = ApiKey::query()->where('merchant_id', $team->id)->sole();

    expect($key->name)->toBe('CI key')
        // The hash stored must never be the plaintext — sha-256 hex is 64 chars.
        ->and(mb_strlen($key->key_hash))->toBe(64);
});

test('billing:api-key defaults the name to Default when omitted', function (): void {
    $team = Team::factory()->create();

    artisan('billing:api-key', ['--merchant' => $team->id])->assertSuccessful();

    expect(ApiKey::query()->where('merchant_id', $team->id)->sole()->name)->toBe('Default');
});

test('billing:api-key fails without a merchant option', function (): void {
    artisan('billing:api-key')
        ->assertFailed()
        ->expectsOutputToContain('--merchant option');
});

test('billing:api-key fails when the merchant id does not exist', function (): void {
    artisan('billing:api-key', ['--merchant' => 99999])
        ->assertFailed()
        ->expectsOutputToContain('does not exist');
});

test('billing:api-key stored key_hash is a sha-256 hex string', function (): void {
    $team = Team::factory()->create();

    artisan('billing:api-key', ['--merchant' => $team->id])->assertSuccessful();

    $key = ApiKey::query()->where('merchant_id', $team->id)->sole();

    expect($key->key_hash)->toMatch('/^[a-f0-9]{64}$/');
});
