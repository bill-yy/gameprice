<?php

namespace App\Http\Controllers;

use App\Models\Game;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

class GameController extends Controller
{
    public function index(Request $request)
    {
        $page = $request->input('page', 1);
        $search = $request->input('search', '');
        $cacheKey = "games.index.page.{$page}.search." . md5($search);

        $games = Cache::remember($cacheKey, 3600, function () use ($request) {
            return Game::query()
                ->with('products.store')
                ->when($request->search, function ($q, $search) {
                    $driver = $q->getConnection()->getDriverName();
                    if ($driver === 'pgsql') {
                        $q->where('title', 'ilike', "%{$search}%");
                    } else {
                        $q->where('title', 'like', "%{$search}%");
                    }
                })
                ->orderByDesc('metacritic_score')
                ->paginate(24)
                ->withQueryString();
        });

        return Inertia::render('Home', [
            'games' => $games,
            'filters' => $request->only('search'),
            'seo' => [
                'title' => 'GamePrice.es - Compara precios de videojuegos',
                'description' => 'Encuentra los mejores precios para tus videojuegos favoritos. Compara ofertas de Eneba, Instant Gaming, Fanatical y más tiendas.',
                'canonical' => url('/'),
                'og' => [
                    'title' => 'GamePrice.es - Compara precios de videojuegos',
                    'description' => 'Encuentra los mejores precios para tus videojuegos favoritos. Compara ofertas de Eneba, Instant Gaming, Fanatical y más tiendas.',
                    'image' => url('/og-image.png'),
                    'type' => 'website',
                ],
            ],
        ]);
    }

    public function show(Game $game)
    {
        $cacheKey = "games.show.{$game->slug}";

        $data = Cache::remember($cacheKey, 1800, function () use ($game) {
            $game->load([
                'products' => fn($q) => $q->whereHas('store', fn($q) => $q->where('is_active', true)),
                'products.store',
                'reviews' => fn($q) => $q->where('is_approved', true)->latest()->limit(10),
            ]);

            $products = $game->products->sortBy('current_price')->values();
            $lowestPrice = $products->first()?->current_price;
            $highestDiscount = $products->max('discount_percentage');

            $schema = [
                '@context' => 'https://schema.org',
                '@type' => 'Product',
                'name' => $game->title,
                'image' => $game->cover_image,
                'description' => $game->description,
                'brand' => [
                    '@type' => 'Brand',
                    'name' => $game->developer ?? $game->publisher,
                ],
                'aggregateRating' => $game->metacritic_score ? [
                    '@type' => 'AggregateRating',
                    'ratingValue' => $game->metacritic_score,
                    'bestRating' => 100,
                    'ratingCount' => 1,
                ] : null,
                'offers' => $products->map(fn($p) => [
                    '@type' => 'Offer',
                    'url' => $p->url,
                    'price' => $p->current_price,
                    'priceCurrency' => $p->currency,
                    'availability' => $p->is_available ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                    'seller' => [
                        '@type' => 'Organization',
                        'name' => $p->store->name,
                    ],
                ])->values()->all(),
            ];

            return compact('game', 'products', 'lowestPrice', 'highestDiscount', 'schema');
        });

        return Inertia::render('GameShow', [
            'game' => $data['game'],
            'products' => $data['products'],
            'reviews' => $data['game']->reviews,
            'seo' => [
                'title' => "{$data['game']->title} - Compara precios | GamePrice",
                'description' => "Compra {$data['game']->title} al mejor precio. Desde {$data['lowestPrice']}€. " . ($data['highestDiscount'] > 0 ? "Ahorra hasta un {$data['highestDiscount']}% " : '') . "en Eneba, Instant Gaming y más tiendas.",
                'canonical' => route('game.show', $data['game']->slug),
                'schema' => $data['schema'],
                'og' => [
                    'title' => "{$data['game']->title} - Compara precios | GamePrice",
                    'description' => "Desde {$data['lowestPrice']}€. Compara ofertas de tiendas oficiales y grey market.",
                    'image' => $data['game']->cover_image,
                    'type' => 'product',
                ],
            ],
        ]);
    }
}
