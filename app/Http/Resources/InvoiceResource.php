<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Invoice
 */
final class InvoiceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'subscription_id' => $this->subscription_id,
            'customer_id'     => $this->customer_id,
            'period_start'    => $this->period_start->toDateString(),
            'period_end'      => $this->period_end->toDateString(),
            'total_amount'    => $this->total_amount,
            'status'          => $this->status->value,
            'items'           => InvoiceItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
