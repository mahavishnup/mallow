<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\PlanPricingCache;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $merchant_id
 * @property string $name
 * @property string $base_price
 * @property int $included_units
 * @property string $overage_rate
 * @property int $billing_cycle_days
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $merchant
 * @property-read Collection<int, Subscription> $subscriptions
 */
#[Fillable(['merchant_id', 'name', 'base_price', 'included_units', 'overage_rate', 'billing_cycle_days', 'is_active'])]
final class Plan extends Model
{
    /** @use HasFactory<\Database\Factories\PlanFactory> */
    use HasFactory;

    /**
     * Attributes whose changes invalidate the cached pricing snapshot.
     */
    private const array PRICING_ATTRIBUTES = ['base_price', 'included_units', 'overage_rate', 'billing_cycle_days'];

    /**
     * Get the merchant (team) that owns this plan.
     *
     * @return BelongsTo<Team, $this>
     */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'merchant_id');
    }

    /**
     * Get the subscriptions on this plan.
     *
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * Boot model events: write-through pricing-cache invalidation (Plan §7).
     */
    protected static function booted(): void
    {
        self::saved(function (Plan $plan): void {
            if ($plan->wasChanged(self::PRICING_ATTRIBUTES)) {
                app(PlanPricingCache::class)->forget($plan->id);
            }
        });

        self::deleted(function (Plan $plan): void {
            app(PlanPricingCache::class)->forget($plan->id);
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'base_price'         => 'decimal:2',
            'included_units'     => 'integer',
            'overage_rate'       => 'decimal:4',
            'billing_cycle_days' => 'integer',
            'is_active'          => 'boolean',
        ];
    }
}
