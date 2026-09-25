<?php

declare(strict_types=1);

use App\Http\Controllers\Api\UsageController;
use App\Http\Middleware\ResolveApiKey;
use Illuminate\Support\Facades\Route;

Route::middleware([ResolveApiKey::class])
    ->group(function (): void {
        Route::post('usage', [UsageController::class, 'store'])
            ->middleware(['throttle:usage', 'throttle:usage-customer'])
            ->name('api.usage.store');
    });
