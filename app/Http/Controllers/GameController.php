<?php

namespace App\Http\Controllers;

use App\Models\Game;
use Illuminate\Http\Request;
use Inertia\Inertia;

class GameController extends Controller
{
    public function index(Request $request)
    {
        $games = Game::query()
            ->with('products.store')
            ->when($request->search, fn($q, $search) => $q->where('title', 'ilike', "%{$search}%"))
            ->orderByDesc('metacritic_score')
            ->paginate(24)
            ->withQueryString();

        return Inertia::render('Home', [
            'games' => $games,
            'filters' => $request->only('search'),
        ]);
    }

    public function show(Game $game)
    {
        $game->load(['products' => fn($q) => $q->whereHas('store', fn($q) => $q->where('is_active', true)), 'products.store']);

        $products = $game->products->sortBy('current_price')->values();

        return Inertia::render('GameShow', [
            'game' => $game,
            'products' => $products,
        ]);
    }
}
