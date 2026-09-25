<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Plan
 */
final class PlanResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'name'               => $this->name,
            'base_price'         => $this->base_price,
            'included_units'     => $this->included_units,
            'overage_rate'       => $this->overage_rate,
            'billing_cycle_days' => $this->billing_cycle_days,
            'is_active'          => $this->is_active,
            'created_at'         => $this->created_at?->toIso8601String(),
        ];
    }
}
