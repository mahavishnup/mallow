<?php

declare(strict_types=1);

use App\Http\Controllers\Api\CustomerController;
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
