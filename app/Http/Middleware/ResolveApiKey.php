<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the merchant (team) from the X-Api-Key header.
 *
 * Stores the resolved ApiKey and merchant Team on the request attributes for
 * downstream middleware, rate limiters, and controllers.
 */
final class ResolveApiKey
{
    /**
     * The request attribute holding the resolved ApiKey.
     */
    public const string ATTRIBUTE_API_KEY = 'billing.api_key';

    /**
     * The request attribute holding the resolved merchant Team.
     */
    public const string ATTRIBUTE_MERCHANT = 'billing.merchant';

    /**
     * Handle an incoming request.
     *
     * Strict by default: a present-but-invalid key always 401s, and a missing
     * key 401s unless the route opts into dual auth via "ResolveApiKey:optional"
     * (used by the dashboard endpoint's session fallback, D4.2).
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, ?string $mode = null): Response
    {
        $headerKey = $request->headers->get('X-Api-Key');

        if ($headerKey === null || $headerKey === '') {
            if ($mode === 'optional') {
                return $next($request);
            }

            return response()->json(['error' => 'missing_api_key'], Response::HTTP_UNAUTHORIZED);
        }

        $apiKey = $this->findApiKey($headerKey);

        if ($apiKey === null) {
            return response()->json(['error' => 'invalid_api_key'], Response::HTTP_UNAUTHORIZED);
        }

        $request->attributes->set(self::ATTRIBUTE_API_KEY, $apiKey);
        $request->attributes->set(self::ATTRIBUTE_MERCHANT, $apiKey->merchant);

        $this->touchLastUsedAt($apiKey);

        return $next($request);
    }

    /**
     * Look up the API key by its sha-256 hash.
     */
    private function findApiKey(string $headerKey): ?ApiKey
    {
        return ApiKey::query()
            ->with('merchant')
            ->where('key_hash', hash('sha256', $headerKey))
            ->first();
    }

    /**
     * Update last_used_at at most once per minute (throttled write).
     */
    private function touchLastUsedAt(ApiKey $apiKey): void
    {
        if ($apiKey->last_used_at !== null && $apiKey->last_used_at->diffInSeconds(now()) < 60) {
            return;
        }

        // Avoid stampeding the row on bursts: a cache guard ensures only one
        // request per minute performs the write.
        $guardKey = 'billing.api_key.last_used.' . $apiKey->id;

        if (Cache::add($guardKey, true, 60)) {
            $apiKey->forceFill(['last_used_at' => now()])->saveQuietly();
        }
    }
}
