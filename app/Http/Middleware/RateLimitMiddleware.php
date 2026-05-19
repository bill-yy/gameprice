<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Symfony\Component\HttpFoundation\Response;

class RateLimitMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var ApiKey|null $keyRecord */
        $keyRecord = $request->attributes->get('api_key_record');
        $identifier = $keyRecord?->key ?? $request->ip();
        $plan = $keyRecord?->plan ?? 'free';

        $config = ApiKey::planConfig($plan);
        $perMinute = $config['rate_limit_per_minute'];
        $dailyLimit = $config['requests_limit_daily'];

        // Rate limit per minute (sliding window via Redis or Cache)
        $minuteKey = "rate_limit:min:{$identifier}:" . now()->format('YmdHi');
        $minuteHits = (int) Cache::get($minuteKey, 0);

        if ($minuteHits >= $perMinute) {
            return response()->json([
                'success' => false,
                'error' => 'Rate limit exceeded. Maximum ' . $perMinute . ' requests per minute for your plan (' . $plan . ').',
            ], 429, [
                'X-RateLimit-Limit' => $perMinute,
                'X-RateLimit-Remaining' => 0,
                'Retry-After' => 60,
                'X-Plan' => $plan,
            ]);
        }

        // Daily limit check (only if not unlimited)
        if ($dailyLimit > 0 && $keyRecord && $keyRecord->hasExceededDailyLimit()) {
            return response()->json([
                'success' => false,
                'error' => 'Daily quota exceeded. Your ' . $plan . ' plan allows ' . $dailyLimit . ' requests per day.',
            ], 429, [
                'X-RateLimit-Limit' => $dailyLimit,
                'X-RateLimit-Remaining' => 0,
                'X-Plan' => $plan,
            ]);
        }

        // Increment counters
        Cache::put($minuteKey, $minuteHits + 1, 60);

        if ($keyRecord) {
            $keyRecord->incrementUsage();
        }

        $response = $next($request);

        $response->headers->set('X-RateLimit-Limit', (string) $perMinute);
        $response->headers->set('X-RateLimit-Remaining', (string) max(0, $perMinute - $minuteHits - 1));
        $response->headers->set('X-Plan', $plan);

        return $response;
    }
}
