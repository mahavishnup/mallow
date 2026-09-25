<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Middleware\ResolveApiKey;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class CustomerController extends Controller
{
    /**
     * List the merchant's customers.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var Team $merchant */
        $merchant = $request->attributes->get(ResolveApiKey::ATTRIBUTE_MERCHANT);

        return CustomerResource::collection(
            Customer::query()
                ->where('merchant_id', $merchant->id)
                ->orderBy('id')
                ->get()
        );
    }
}
