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
 * @property int $invoice_id
 * @property int $plan_id
 * @property Carbon $segment_start
 * @property Carbon $segment_end
 * @property string $prorated_base
 * @property int $included_units
 * @property int $billable_usage
 * @property int $overage_units
 * @property string $overage_amount
 * @property string $line_total
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Invoice $invoice
 * @property-read Plan $plan
 */
#[Fillable(['invoice_id', 'plan_id', 'segment_start', 'segment_end', 'prorated_base', 'included_units', 'billable_usage', 'overage_units', 'overage_amount', 'line_total'])]
final class InvoiceItem extends Model
{
    /** @use HasFactory<\Database\Factories\InvoiceItemFactory> */
    use HasFactory;

    /**
     * Get the invoice this line belongs to.
     *
     * @return BelongsTo<Invoice, $this>
     */
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /**
     * Get the plan billed on this line.
     *
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'segment_start'  => 'date',
            'segment_end'    => 'date',
            'prorated_base'  => 'decimal:2',
            'included_units' => 'integer',
            'billable_usage' => 'integer',
            'overage_units'  => 'integer',
            'overage_amount' => 'decimal:2',
            'line_total'     => 'decimal:2',
        ];
    }
}
