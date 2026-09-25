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
 * @property int $merchant_id
 * @property int $customer_id
 * @property Carbon $usage_date
 * @property int $total_quantity
 * @property int $event_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Customer $customer
 * @property-read Team $merchant
 */
#[Fillable(['merchant_id', 'customer_id', 'usage_date', 'total_quantity', 'event_count'])]
final class DailyUsage extends Model
{
    /** @use HasFactory<\Database\Factories\DailyUsageFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'daily_usage';

    /**
     * Get the customer this aggregate belongs to.
     *
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the merchant (team) this aggregate belongs to.
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
            'usage_date'     => 'date',
            'total_quantity' => 'integer',
            'event_count'    => 'integer',
        ];
    }
}
