<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;

Route::get('/debug-health', function () {
    return response()->json(['status' => 'ok', 'time' => now()->toIso8601String()]);
});

Route::get('/debug-db-test', function () {
    try {
        $games = DB::table('games')->count();
        $products = DB::table('products')->count();
        $jobs = DB::table('jobs')->count();
        return response()->json(['games' => $games, 'products' => $products, 'jobs' => $jobs]);
    } catch (\Throwable $e) {
        return response()->json(['error' => $e->getMessage()]);
    }
});

Route::get('/debug-products/{slug}', function ($slug) {
    try {
        $game = DB::table('games')->where('slug', $slug)->first();
        if (!$game) {
            return response()->json(['error' => 'Game not found']);
        }
        $products = DB::table('products')
            ->where('game_id', $game->id)
            ->join('stores', 'products.store_id', '=', 'stores.id')
            ->select('products.*', 'stores.name as store_name', 'stores.slug as store_slug')
            ->get();
        return response()->json([
            'game' => $game->title,
            'product_count' => $products->count(),
            'products' => $products,
        ]);
    } catch (\Throwable $e) {
        return response()->json(['error' => $e->getMessage()]);
    }
});
