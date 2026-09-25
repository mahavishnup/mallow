<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\InvoiceStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $subscription_id
 * @property int $merchant_id
 * @property int $customer_id
 * @property Carbon $period_start
 * @property Carbon $period_end
 * @property string $total_amount
 * @property InvoiceStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Subscription $subscription
 * @property-read Team $merchant
 * @property-read Customer $customer
 * @property-read Collection<int, InvoiceItem> $items
 */
#[Fillable(['subscription_id', 'merchant_id', 'customer_id', 'period_start', 'period_end', 'total_amount', 'status'])]
final class Invoice extends Model
{
    /** @use HasFactory<\Database\Factories\InvoiceFactory> */
    use HasFactory;

    /**
     * The model's default attributes.
     *
     * @var array<string, string>
     */
    protected $attributes = [
        'status' => InvoiceStatus::Draft->value,
    ];

    /**
     * Get the subscription this invoice bills.
     *
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * Get the merchant (team) this invoice belongs to.
     *
     * @return BelongsTo<Team, $this>
     */
    public function merchant(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'merchant_id');
    }

    /**
     * Get the customer this invoice belongs to.
     *
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the per-segment billing lines.
     *
     * @return HasMany<InvoiceItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end'   => 'date',
            'total_amount' => 'decimal:2',
            'status'       => InvoiceStatus::class,
        ];
    }
}
