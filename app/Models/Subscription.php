<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $customer_id
 * @property int $plan_id
 * @property int $merchant_id
 * @property Carbon $starts_at
 * @property Carbon|null $ends_at
 * @property SubscriptionStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Customer $customer
 * @property-read Plan $plan
 * @property-read Team $merchant
 * @property-read Collection<int, SubscriptionSegment> $segments
 */
#[Fillable(['customer_id', 'plan_id', 'merchant_id', 'starts_at', 'ends_at', 'status'])]
final class Subscription extends Model
{
    /** @use HasFactory<\Database\Factories\SubscriptionFactory> */
    use HasFactory;

    /**
     * The model's attributes default state.
     *
     * @var array<string, string>
     */
    protected $attributes = [
        'status' => SubscriptionStatus::Active->value,
    ];

    /**
     * Get the customer that owns this subscription.
     *
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the current plan of this subscription (denormalized for fast reads).
     *
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * Get the merchant (team) of this subscription (denormalized for dashboard lookups).
     *
     * @return BelongsTo<Team, $this>
     */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'merchant_id');
    }

    /**
     * Get the plan-period history segments, ordered by start date.
     *
     * @return HasMany<SubscriptionSegment, $this>
     */
    public function segments(): HasMany
    {
        return $this->hasMany(SubscriptionSegment::class)->orderBy('starts_at');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'date',
            'ends_at'   => 'date',
            'status'    => SubscriptionStatus::class,
        ];
    }
}
