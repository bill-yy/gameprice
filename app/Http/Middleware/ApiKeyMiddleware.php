<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiKeyMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $validKeys = config('api.keys', []);

        // En local, permitir bypass si no hay keys reales configuradas
        if (app()->environment('local')) {
            if (empty($validKeys) || (count($validKeys) === 1 && $validKeys[0] === 'dev-key-change-me-in-production')) {
                return $next($request);
            }
        }

        // En producción (y local con keys configuradas), siempre requerir key
        if (empty($validKeys)) {
            return response()->json([
                'success' => false,
                'error' => 'API authentication not configured.',
            ], 401);
        }

        $apiKey = $request->header('X-API-Key');

        if (! $apiKey) {
            return response()->json([
                'success' => false,
                'error' => 'Missing API key. Provide X-API-Key header.',
            ], 401);
        }

        if (! in_array($apiKey, $validKeys, true)) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid API key.',
            ], 401);
        }

        return $next($request);
    }
}
