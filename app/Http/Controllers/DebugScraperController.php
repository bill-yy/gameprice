<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\Product;
use App\Services\DebugScraperService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class DebugScraperController extends Controller
{
    public function diagnose(Game $game): JsonResponse
    {
        $products = Product::where('game_id', $game->id)
            ->with('store:id,name,slug')
            ->get()
            ->map(fn ($p) => [
                'store' => $p->store?->name ?? $p->store?->slug ?? 'Unknown',
                'platform' => $p->platform,
                'price' => $p->current_price,
                'is_real_price' => $p->is_real_price,
                'price_fetched_at' => $p->price_fetched_at?->toIso8601String(),
            ]);

        $pendingJobs = 0;
        if (config('queue.default') === 'database') {
            try {
                $pendingJobs = DB::table('jobs')->count();
            } catch (\Throwable $e) {
                $pendingJobs = 'error: ' . $e->getMessage();
            }
        }

        return response()->json([
            'game' => [
                'id' => $game->id,
                'title' => $game->title,
                'slug' => $game->slug,
            ],
            'products_count' => $products->count(),
            'products' => $products,
            'scrapers' => array_keys(DebugScraperService::SCRAPERS),
            'queue_connection' => config('queue.default'),
            'pending_jobs' => $pendingJobs,
        ]);
    }

    public function runAllScrapers(Game $game): JsonResponse
    {
        $service = new DebugScraperService();
        $results = $service->diagnose($game->title);

        return response()->json($results);
    }
}
