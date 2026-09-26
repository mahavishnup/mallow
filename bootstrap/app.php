<?php

declare(strict_types=1);

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetTeamUrlDefaults;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            SetTeamUrlDefaults::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Uniform JSON error contract for the machine API: every failure on
        // api/* is {"message": "..."} with the proper status — never an HTML
        // error page or stack trace. ValidationException returns null so
        // Laravel's default handler renders the standard 422 errors payload.
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*') || $e instanceof ValidationException) {
                return null;
            }

            $status = $e instanceof HttpExceptionInterface
                ? $e->getStatusCode()
                : 500;

            $title = match ($status) {
                401     => 'unauthenticated',
                403     => 'forbidden',
                404     => 'not_found',
                422     => 'validation_failed',
                429     => 'rate_limited',
                500     => 'server_error',
                default => 'error',
            };

            $message = $e instanceof HttpExceptionInterface && $e->getMessage() !== ''
                ? $e->getMessage()
                : $title;

            // Preserve HttpException headers (Retry-After on 429s etc.).
            return response()->json([
                'message' => $message,
                'error'   => $title,
            ], $status, $e instanceof HttpExceptionInterface ? $e->getHeaders() : []);
        });
    })->create();
