<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\PriceAlert;
use App\Models\Product;
use App\Models\Store;
use Inertia\Inertia;

class AdminController extends Controller
{
    public function dashboard()
    {
        $stats = [
            'total_games' => Game::count(),
            'total_products' => Product::count(),
            'active_stores' => Store::where('is_active', true)->count(),
            'active_alerts' => PriceAlert::where('is_active', true)->count(),
        ];

        return Inertia::render('AdminDashboard', [
            'stats' => $stats,
        ]);
    }
}
