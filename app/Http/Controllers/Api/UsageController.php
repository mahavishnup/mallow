<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Billing\RecordUsageAction;
use App\Http\Controllers\Controller;
use App\Http\Middleware\ResolveApiKey;
use App\Http\Requests\Api\RecordUsageRequest;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

final class UsageController extends Controller
{
    /**
     * Record a usage event (idempotent via idempotency_key).
     */
    public function store(RecordUsageRequest $request, RecordUsageAction $action): JsonResponse
    {
        /** @var Team $merchant */
        $merchant = $request->attributes->get(ResolveApiKey::ATTRIBUTE_MERCHANT);

        $result = $action->handle($merchant, $request);

        if ($result['stored'] === false) {
            return response()->json(['duplicate' => true], Response::HTTP_OK);
        }

        $event = $result['event'];

        return response()->json([
            'data' => [
                'id'          => $event->id,
                'customer_id' => $event->customer_id,
                'usage_date'  => $event->usage_date->toDateString(),
                'quantity'    => $event->quantity,
                'type'        => $event->type,
            ],
        ], Response::HTTP_CREATED);
    }
}
