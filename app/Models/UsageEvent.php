<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string|null $uuid
 * @property int $merchant_id
 * @property int $customer_id
 * @property Carbon $usage_date
 * @property int $quantity
 * @property string $type
 * @property Carbon|null $aggregated_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Customer $customer
 * @property-read Team $merchant
 */
#[Fillable(['uuid', 'merchant_id', 'customer_id', 'usage_date', 'quantity', 'type'])]
final class UsageEvent extends Model
{
    /** @use HasFactory<\Database\Factories\UsageEventFactory> */
    use HasFactory;

    /**
     * Get the customer this event belongs to.
     *
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the merchant (team) this event belongs to.
     *
     * @return BelongsTo<Team, $this>
     */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'merchant_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'usage_date'    => 'date',
            'aggregated_at' => 'datetime',
        ];
    }
}
