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
     * Record a usage event for the authenticated merchant's customer (idempotent).
     *
     * The merchant (tenant) is resolved from the X-Api-Key header; the customer
     * must belong to that merchant and hold an active subscription covering
     * usage_date. Retrying with the same idempotency_key returns
     * 200 {duplicate: true} and never counts the usage twice.
     *
     * @authenticated
     *
     * @group Usage
     *
     * @bodyParam customer_id integer required The customer ID (must belong to the authenticated merchant). Example: 1
     * @bodyParam usage_date string required The usage date, today or earlier (Y-m-d). Example: 2026-09-26
     * @bodyParam quantity integer required Usage quantity, min 1. Example: 250
     * @bodyParam type string required Usage type (alpha-dash), informational — all types bill against one allowance. Example: api_calls
     * @bodyParam idempotency_key string required UUID v4. Retries with the same key are safe. Example: 0d0f4c2e-1111-4111-8111-000000000001
     *
     * @response 201 {"data": {"id": 1, "customer_id": 1, "usage_date": "2026-09-26", "quantity": 250, "type": "api_calls"}}
     * @response 200 {"duplicate": true} {"Duplicate idempotency_key: the event was already stored; nothing is counted twice."}
     * @response status=404 {"message": "Not found."} {"Customer does not belong to this merchant."}
     * @response status=422 {"message": "...", "errors": {"customer_id": ["no_active_subscription"]}} {"Customer has no active subscription covering usage_date."}
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
