<?php

namespace App\Http\Controllers;

use App\Models\Game;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

class GenreController extends Controller
{
    public function show(Request $request, string $genre)
    {
        $genre = ucfirst(strtolower($genre));
        $page = $request->input('page', 1);
        $cacheKey = "games.genre.{$genre}.page.{$page}";

        $games = Cache::remember($cacheKey, 1800, function () use ($genre) {
            return Game::query()
                ->with(['products' => fn ($q) => $q->whereHas('store', fn ($q) => $q->where('is_active', true)), 'products.store'])
                ->whereJsonContains('genres', $genre)
                ->orderByDesc('metacritic_score')
                ->paginate(24)
                ->withQueryString();
        });

        $popularGenres = ['Action', 'Adventure', 'RPG', 'Strategy', 'Shooter', 'Indie', 'Simulation', 'Sports'];

        return Inertia::render('GenreShow', [
            'genre' => $genre,
            'games' => $games,
            'popularGenres' => $popularGenres,
            'seo' => [
                'title' => "Juegos de {$genre} - Mejores precios | GamePrice",
                'description' => "Descubre los mejores precios para juegos de {$genre}. Compara ofertas de Eneba, Instant Gaming, Fanatical y más tiendas.",
                'canonical' => route('genre.show', $genre),
                'og' => [
                    'title' => "Juegos de {$genre} - Mejores precios | GamePrice",
                    'description' => "Descubre los mejores precios para juegos de {$genre}. Compara ofertas de múltiples tiendas.",
                    'image' => url('/og-image.png'),
                    'type' => 'website',
                ],
            ],
        ]);
    }
}
