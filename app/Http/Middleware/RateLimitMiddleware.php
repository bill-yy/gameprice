<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class RateLimitMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = 'rate_limit:' . ($request->header('X-API-Key') ?? $request->ip());
        $limit = (int) config('api.rate_limit', 100);

        $hits = (int) Cache::get($key, 0);

        if ($hits >= $limit) {
            return response()->json([
                'success' => false,
                'error' => 'Rate limit exceeded. Maximum ' . $limit . ' requests per hour.',
            ], 429, [
                'X-RateLimit-Limit' => $limit,
                'X-RateLimit-Remaining' => 0,
                'Retry-After' => 3600,
            ]);
        }

        Cache::put($key, $hits + 1, 3600);

        $response = $next($request);

        $response->headers->set('X-RateLimit-Limit', (string) $limit);
        $response->headers->set('X-RateLimit-Remaining', (string) max(0, $limit - $hits - 1));

        return $response;
    }
}
