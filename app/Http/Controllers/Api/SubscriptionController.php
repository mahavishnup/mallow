<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Billing\ChangeSubscriptionPlanAction;
use App\Actions\Billing\CreateSubscriptionAction;
use App\Actions\Billing\GenerateInvoiceAction;
use App\Http\Controllers\Controller;
use App\Http\Middleware\ResolveApiKey;
use App\Http\Requests\Api\ChangeSubscriptionPlanRequest;
use App\Http\Requests\Api\GenerateInvoiceRequest;
use App\Http\Requests\Api\StoreSubscriptionRequest;
use App\Http\Resources\InvoiceResource;
use App\Http\Resources\SubscriptionResource;
use App\Models\Subscription;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class SubscriptionController extends Controller
{
    /**
     * Create a subscription for a merchant's customer.
     */
    public function store(StoreSubscriptionRequest $request, CreateSubscriptionAction $action): JsonResponse
    {
        /** @var Team $merchant */
        $merchant = $request->attributes->get(ResolveApiKey::ATTRIBUTE_MERCHANT);

        $subscription = $action->handle($merchant->id, [
            'customer_id' => $request->integer('customer_id'),
            'plan_id'     => $request->integer('plan_id'),
            'starts_at'   => $request->input('starts_at'),
        ]);

        return (new SubscriptionResource($subscription->load('segments')))
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    /**
     * Change the subscription's plan (mid-cycle safe).
     */
    public function changePlan(ChangeSubscriptionPlanRequest $request, Subscription $subscription, ChangeSubscriptionPlanAction $action): JsonResponse
    {
        $this->assertOwnership($request, $subscription);

        $subscription = $action->handle($subscription, [
            'plan_id'        => $request->integer('plan_id'),
            'effective_date' => $request->input('effective_date'),
        ]);

        return (new SubscriptionResource($subscription->load('segments')))->response();
    }

    /**
     * Generate the invoice for a billing period (current cycle by default).
     *
     * Runs the action synchronously for immediate feedback; the queued job
     * exists for batch/scheduled generation (see GenerateInvoiceJob).
     */
    public function generateInvoice(GenerateInvoiceRequest $request, Subscription $subscription, GenerateInvoiceAction $action): JsonResponse
    {
        $this->assertOwnership($request, $subscription);

        $invoice = $action->handle($subscription, [
            'period_start' => $request->input('period_start'),
            'period_end'   => $request->input('period_end'),
        ]);

        // 200 (not 201): generation is idempotent — a rebuild is not a creation.
        return (new InvoiceResource($invoice->load('items')))
            ->response()
            ->setStatusCode(Response::HTTP_OK);
    }

    /**
     * Ensure the subscription belongs to the authenticated merchant.
     */
    private function assertOwnership(Request $request, Subscription $subscription): void
    {
        /** @var Team $merchant */
        $merchant = $request->attributes->get(ResolveApiKey::ATTRIBUTE_MERCHANT);

        if ($subscription->merchant_id !== $merchant->id) {
            abort(404);
        }
    }
}
