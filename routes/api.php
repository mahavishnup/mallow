<?php

declare(strict_types=1);

use App\Http\Controllers\Api\CustomerController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\PlanController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\UsageController;
use App\Http\Middleware\ResolveApiKey;
use Illuminate\Support\Facades\Route;

Route::middleware([ResolveApiKey::class])
    ->group(function (): void {
        Route::post('usage', [UsageController::class, 'store'])
            ->middleware(['throttle:usage', 'throttle:usage-customer'])
            ->name('api.usage.store');

        Route::get('plans', [PlanController::class, 'index'])->name('api.plans.index');
        Route::post('plans', [PlanController::class, 'store'])->name('api.plans.store');

        Route::get('customers', [CustomerController::class, 'index'])->name('api.customers.index');

        Route::post('subscriptions', [SubscriptionController::class, 'store'])->name('api.subscriptions.store');
        Route::patch('subscriptions/{subscription}/plan', [SubscriptionController::class, 'changePlan'])->name('api.subscriptions.change-plan');
        Route::post('subscriptions/{subscription}/invoice', [SubscriptionController::class, 'generateInvoice'])->name('api.subscriptions.generate-invoice');
    });

Route::middleware([
    ResolveApiKey::class . ':optional',
    // Cookie + session middleware so a browser session can authenticate the
    // fallback path (the default api group has none of these). Authentication
    // itself is enforced by the controller, keeping key-only requests valid.
    Illuminate\Cookie\Middleware\EncryptCookies::class,
    Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
    Illuminate\Session\Middleware\StartSession::class,
])
    ->group(function (): void {
        // Dual auth (D4.2): X-Api-Key resolves the merchant; without a key the
        // controller falls back to an authenticated session user's team
        // membership.
        Route::get('merchants/{id}/dashboard', [DashboardController::class, '__invoke'])
            ->name('api.merchants.dashboard')
            // Merchant ids are numeric; a tenant *slug* belongs to the web
            // route, so a slug here must 404 cleanly instead of tripping the
            // controller's int type-hint.
            ->whereNumber('id');
    });
