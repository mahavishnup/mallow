<?php

declare(strict_types=1);

namespace App\Providers;

use App\Http\Middleware\ResolveApiKey;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

final class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRateLimiters();
    }

    /**
     * Configure the billing API rate limiters (Plan §8).
     */
    protected function configureRateLimiters(): void
    {
        RateLimiter::for('usage', function (Request $request): array {
            $merchant = $request->attributes->get(ResolveApiKey::ATTRIBUTE_MERCHANT);

            return [
                Limit::perMinute((int) config('billing.ingestion.merchant_per_minute'))
                    ->by('merchant:' . ($merchant !== null ? $merchant->id : $request->ip())),
            ];
        });

        RateLimiter::for('usage-customer', function (Request $request): array {
            $merchant = $request->attributes->get(ResolveApiKey::ATTRIBUTE_MERCHANT);
            $customerId = (int) $request->input('customer_id', 0);

            return [
                Limit::perMinute((int) config('billing.ingestion.customer_per_minute'))
                    ->by('merchant:' . ($merchant !== null ? $merchant->id : $request->ip()) . ':customer:' . $customerId),
            ];
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(
            fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
