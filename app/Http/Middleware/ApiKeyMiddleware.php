<?php

namespace App\Http\Middleware;

use App\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiKeyMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // En local, permitir bypass si no hay keys reales configuradas
        if (app()->environment('local')) {
            $validKeys = config('api.keys', []);
            if (empty($validKeys) || (count($validKeys) === 1 && $validKeys[0] === 'dev-key-change-me-in-production')) {
                return $next($request);
            }
        }

        $apiKey = $request->header('X-API-Key');

        if (! $apiKey) {
            return response()->json([
                'success' => false,
                'error' => 'Missing API key. Provide X-API-Key header.',
            ], 401);
        }

        $keyRecord = ApiKey::where('key', $apiKey)->where('is_active', true)->first();

        if (! $keyRecord) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid or revoked API key.',
            ], 401);
        }

        if ($keyRecord->expires_at && $keyRecord->expires_at->isPast()) {
            return response()->json([
                'success' => false,
                'error' => 'API key has expired.',
            ], 401);
        }

        // Attach key record to request for downstream middleware/controllers
        $request->attributes->set('api_key_record', $keyRecord);

        return $next($request);
    }
}
