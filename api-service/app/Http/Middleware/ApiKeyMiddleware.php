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

        if (empty($validKeys) || (count($validKeys) === 1 && $validKeys[0] === 'dev-key-change-me-in-production')) {
            return $next($request);
        }

        $apiKey = $request->header('X-API-Key');

        if (!$apiKey) {
            return response()->json([
                'success' => false,
                'error' => 'Missing API key. Provide X-API-Key header.',
            ], 401);
        }

        if (!in_array($apiKey, $validKeys, true)) {
            return response()->json([
                'success' => false,
                'error' => 'Invalid API key.',
            ], 401);
        }

        return $next($request);
    }
}
