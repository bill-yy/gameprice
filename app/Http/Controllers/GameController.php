<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\Product;
use App\Models\Store;
use App\Models\Voucher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

class GameController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->input('search', '');
        $cacheKey = 'games.index.' . md5(json_encode($request->all()));

        $games = Cache::remember($cacheKey, 3600, function () use ($request) {
            $query = Game::query()
                ->with('products.store')
                ->when($request->search, function ($q, $search) {
                    $driver = $q->getConnection()->getDriverName();
                    if ($driver === 'pgsql') {
                        $q->where('title', 'ilike', "%{$search}%");
                    } else {
                        $q->where('title', 'like', "%{$search}%");
                    }
                });

            if ($request->filled('price_min') || $request->filled('price_max')) {
                $query->whereHas('products', function ($q) use ($request) {
                    $q->where('is_real_price', true);
                    if ($request->filled('price_min')) {
                        $q->where('current_price', '>=', $request->price_min);
                    }
                    if ($request->filled('price_max')) {
                        $q->where('current_price', '<=', $request->price_max);
                    }
                });
            }

            if ($request->filled('discount_min')) {
                $query->whereHas('products', function ($q) use ($request) {
                    $q->where('is_real_price', true)
                      ->where('discount_percent', '>=', $request->discount_min);
                });
            }

            if ($request->filled('region')) {
                $query->whereHas('products', function ($q) use ($request) {
                    $q->where('region', $request->region);
                });
            }

            if ($request->filled('store')) {
                $storeSlugs = explode(',', $request->store);
                $query->whereHas('products.store', function ($q) use ($storeSlugs) {
                    $q->whereIn('slug', $storeSlugs);
                });
            }

            $sort = $request->input('sort', '');
            switch ($sort) {
                case 'price_asc':
                    $query->orderBy(
                        Product::selectRaw('MIN(current_price)')
                            ->whereColumn('products.game_id', 'games.id')
                            ->where('is_real_price', true)
                    );
                    break;
                case 'price_desc':
                    $query->orderByDesc(
                        Product::selectRaw('MIN(current_price)')
                            ->whereColumn('products.game_id', 'games.id')
                            ->where('is_real_price', true)
                    );
                    break;
                case 'discount_desc':
                    $query->orderByDesc(
                        Product::selectRaw('MAX(discount_percent)')
                            ->whereColumn('products.game_id', 'games.id')
                            ->where('is_real_price', true)
                    );
                    break;
                case 'release_desc':
                    $query->orderByDesc('release_date');
                    break;
                case 'name_asc':
                    $query->orderBy('title');
                    break;
                default:
                    $query->orderByDesc('metacritic_score');
                    break;
            }

            $paginator = $query->paginate(24)->withQueryString();

            return $paginator->toArray();
        });

        $trending = Game::query()
            ->with(['products' => fn($q) => $q->where('is_real_price', true)->orderBy('current_price')])
            ->whereHas('products', fn($q) => $q->where('is_real_price', true))
            ->orderByDesc('metacritic_score')
            ->limit(8)
            ->get();

        $bestDeals = Game::query()
            ->with(['products' => fn($q) => $q->where('is_real_price', true)->orderBy('current_price')])
            ->whereHas('products', fn($q) => $q->where('is_real_price', true)->where('discount_percent', '>', 50))
            ->orderByDesc(
                Product::selectRaw('MAX(discount_percent)')
                    ->whereColumn('products.game_id', 'games.id')
            )
            ->limit(8)
            ->get();

        $newReleases = Game::query()
            ->with(['products' => fn($q) => $q->where('is_real_price', true)->orderBy('current_price')])
            ->whereNotNull('release_date')
            ->where('release_date', '>=', now()->subDays(30))
            ->whereHas('products', fn($q) => $q->where('is_real_price', true))
            ->orderByDesc('release_date')
            ->limit(8)
            ->get();

        if ($search && count($games['data'] ?? []) === 0) {
            $onDemand = app(\App\Services\OnDemandSearchService::class);
            $found = $onDemand->search($search);

            if ($found) {
                return redirect()->route('game.show', $found->slug);
            }
        }

        $stores = Store::where('is_active', true)->get(['id', 'name', 'slug']);
        $regions = Product::distinct()->pluck('region')->filter()->sort()->values();

        return Inertia::render('Home', [
            'games' => $games,
            'trendingGames' => $trending,
            'bestDeals' => $bestDeals,
            'newReleases' => $newReleases,
            'stores' => $stores,
            'regions' => $regions,
            'filters' => $request->only(['search', 'price_min', 'price_max', 'discount_min', 'region', 'store', 'sort']),
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

            return [
                'game' => $game->toArray(),
                'products' => $products->values()->toArray(),
                'reviews' => $game->reviews->toArray(),
                'lowestPrice' => $lowestPrice,
                'highestDiscount' => $highestDiscount,
                'schema' => $schema,
                'vouchers' => Voucher::whereIn('store_id', $products->pluck('store_id')->unique())
                    ->where('is_active', true)
                    ->where('valid_from', '<=', now())
                    ->where('valid_until', '>=', now())
                    ->get()
                    ->keyBy('store_id')
                    ->toArray(),
                'priceHistories' => $products->mapWithKeys(fn($p) => [
                    $p->id => $p->priceHistory()
                        ->where('recorded_at', '>=', now()->subMonths(6))
                        ->orderBy('recorded_at')
                        ->get(['price', 'recorded_at'])
                        ->toArray()
                ])->toArray(),
            ];
        });

        return Inertia::render('GameShow', [
            'game' => $data['game'],
            'products' => $data['products'],
            'reviews' => $data['reviews'],
            'priceHistories' => $data['priceHistories'],
            'vouchers' => $data['vouchers'],
            'seo' => [
                'title' => "{$data['game']['title']} - Compara precios | GamePrice",
                'description' => "Compra {$data['game']['title']} al mejor precio. Desde {$data['lowestPrice']}€. " . ($data['highestDiscount'] > 0 ? "Ahorra hasta un {$data['highestDiscount']}% " : '') . "en Eneba, Instant Gaming y más tiendas.",
                'canonical' => route('game.show', $data['game']['slug']),
                'schema' => $data['schema'],
                'og' => [
                    'title' => "{$data['game']['title']} - Compara precios | GamePrice",
                    'description' => "Desde {$data['lowestPrice']}€. Compara ofertas de tiendas oficiales y grey market.",
                    'image' => $data['game']['cover_image'],
                    'type' => 'product',
                ],
            ],
        ]);
    }
}
