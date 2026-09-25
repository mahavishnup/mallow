<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Middleware\ResolveApiKey;
use App\Http\Requests\Api\StorePlanRequest;
use App\Http\Resources\PlanResource;
use App\Models\Plan;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

final class PlanController extends Controller
{
    /**
     * List the merchant's plans (active only by default).
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var Team $merchant */
        $merchant = $request->attributes->get(ResolveApiKey::ATTRIBUTE_MERCHANT);

        $plans = Plan::query()
            ->where('merchant_id', $merchant->id)
            ->when($request->boolean('include_inactive') === false, fn ($query) => $query->where('is_active', true))
            ->orderBy('id')
            ->get();

        return PlanResource::collection($plans);
    }

    /**
     * Create a plan for the merchant.
     */
    public function store(StorePlanRequest $request): JsonResponse
    {
        /** @var Team $merchant */
        $merchant = $request->attributes->get(ResolveApiKey::ATTRIBUTE_MERCHANT);

        $plan = Plan::query()->create([
            ...$request->validated(),
            'merchant_id' => $merchant->id,
        ]);

        return (new PlanResource($plan))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }
}
