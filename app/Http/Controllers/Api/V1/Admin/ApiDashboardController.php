<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\Controller;
use App\Models\ApiKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ApiDashboardController extends Controller
{
    public function stats(): JsonResponse
    {
        $totalKeys = ApiKey::count();
        $activeKeys = ApiKey::where('is_active', true)->count();
        $totalRequestsToday = ApiKey::sum('requests_count_today');

        $byPlan = ApiKey::select('plan', DB::raw('count(*) as count'), DB::raw('sum(requests_count_today) as requests'))
            ->groupBy('plan')
            ->get();

        $topKeys = ApiKey::orderByDesc('requests_count_today')
            ->limit(10)
            ->get(['id', 'name', 'plan', 'requests_count_today', 'last_used_at']);

        return response()->json([
            'success' => true,
            'stats' => [
                'total_keys' => $totalKeys,
                'active_keys' => $activeKeys,
                'total_requests_today' => $totalRequestsToday,
                'by_plan' => $byPlan,
                'top_keys' => $topKeys,
            ],
        ]);
    }

    public function keys(): JsonResponse
    {
        $keys = ApiKey::orderByDesc('created_at')
            ->paginate(50);

        return response()->json([
            'success' => true,
            'keys' => $keys,
        ]);
    }

    public function createKey(): JsonResponse
    {
        $validated = request()->validate([
            'name' => 'nullable|string|max:255',
            'plan' => 'required|in:free,basic,pro,ultra',
            'expires_days' => 'nullable|integer|min:1|max:365',
        ]);

        $key = ApiKey::generate(
            $validated['plan'],
            $validated['name'] ?? null,
            $validated['expires_days'] ?? null
        );

        return response()->json([
            'success' => true,
            'api_key' => $key->key,
            'plan' => $key->plan,
            'name' => $key->name,
        ], 201);
    }

    public function revokeKey(int $id): JsonResponse
    {
        $key = ApiKey::findOrFail($id);
        $key->update(['is_active' => false]);

        return response()->json([
            'success' => true,
            'message' => 'API key revoked.',
        ]);
    }
}
