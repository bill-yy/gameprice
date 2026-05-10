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
        $game->load(['products' => fn($q) => $q->whereHas('store', fn($q) => $q->where('is_active', true)), 'products.store']);

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

        return Inertia::render('GameShow', [
            'game' => $game,
            'products' => $products,
            'seo' => [
                'title' => "{$game->title} - Compara precios | GamePrice",
                'description' => "Compra {$game->title} al mejor precio. Desde {$lowestPrice}€. " . ($highestDiscount > 0 ? "Ahorra hasta un {$highestDiscount}% " : '') . "en Eneba, Instant Gaming y más tiendas.",
                'canonical' => route('game.show', $game->slug),
                'schema' => $schema,
                'og' => [
                    'title' => "{$game->title} - Compara precios | GamePrice",
                    'description' => "Desde {$lowestPrice}€. Compara ofertas de tiendas oficiales y grey market.",
                    'image' => $game->cover_image,
                    'type' => 'product',
                ],
            ],
        ]);
    }
}
