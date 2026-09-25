<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\SubscriptionSegment
 */
final class SubscriptionSegmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id'        => $this->id,
            'plan_id'   => $this->plan_id,
            'starts_at' => $this->starts_at->toDateString(),
            'ends_at'   => $this->ends_at->toDateString(),
        ];
    }
}
