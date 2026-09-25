<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\InvoiceItem
 */
final class InvoiceItemResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'plan_id'        => $this->plan_id,
            'segment_start'  => $this->segment_start->toDateString(),
            'segment_end'    => $this->segment_end->toDateString(),
            'prorated_base'  => $this->prorated_base,
            'included_units' => $this->included_units,
            'billable_usage' => $this->billable_usage,
            'overage_units'  => $this->overage_units,
            'overage_amount' => $this->overage_amount,
            'line_total'     => $this->line_total,
        ];
    }
}
