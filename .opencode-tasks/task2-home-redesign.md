# Task: Home Page Redesign

## Context
This is a Laravel 12 + Vue 3 + Inertia project at /tmp/gameprice-repo.

## What exists
- Home.vue is a simple grid of games with pagination
- Game model has: title, slug, cover_image, release_date, metacritic_score, genres, etc.
- Product model has: current_price, original_price, discount_percentage, is_real_price
- GameController::index() returns paginated games with products.store

## What to build

### 1. Update app/Http/Controllers/GameController.php index() method
Add these computed collections to pass to Home.vue:

```php
// Trending: games with most real-price products (or most recent updates)
$trending = Game::query()
    ->with(['products' => fn($q) => $q->where('is_real_price', true)->orderBy('current_price')])
    ->whereHas('products', fn($q) => $q->where('is_real_price', true))
    ->orderByDesc('metacritic_score')
    ->limit(8)
    ->get();

// Best Deals: games with highest discount % among real prices
$bestDeals = Game::query()
    ->with(['products' => fn($q) => $q->where('is_real_price', true)->orderBy('current_price')])
    ->whereHas('products', fn($q) => $q->where('is_real_price', true)->where('discount_percentage', '>', 50))
    ->orderByDesc(
        Product::selectRaw('MAX(discount_percentage)')
            ->whereColumn('products.game_id', 'games.id')
    )
    ->limit(8)
    ->get();

// New Releases: games released in last 30 days
$newReleases = Game::query()
    ->with(['products' => fn($q) => $q->where('is_real_price', true)->orderBy('current_price')])
    ->whereNotNull('release_date')
    ->where('release_date', '>=', now()->subDays(30))
    ->whereHas('products', fn($q) => $q->where('is_real_price', true))
    ->orderByDesc('release_date')
    ->limit(8)
    ->get();
```

Pass them to Inertia::render('Home', ...) as:
- 'trendingGames' => $trending
- 'bestDeals' => $bestDeals
- 'newReleases' => $newReleases

### 2. Update resources/js/Pages/Home.vue
Redesign the home page with these sections IN ORDER:

#### Hero Section
- Big title: "Encuentra los mejores precios de videojuegos"
- Subtitle: "Compara ofertas de tiendas oficiales y grey market en segundos"
- SearchBar component (already exists, import it)

#### 🔥 Trending Section
- Title: "Juegos Trending"
- Horizontal scrollable row of GameCard components
- Pass the trendingGames data

#### 💎 Best Deals Section
- Title: "Mejores Descuentos"
- Horizontal scrollable row of GameCard components
- Show discount badge on each card
- Pass the bestDeals data

#### 🆕 New Releases Section
- Title: "Últimos Lanzamientos"
- Horizontal scrollable row of GameCard components
- Show release date on each card
- Pass the newReleases data

#### All Games Section (existing)
- Title: "Todos los juegos"
- Keep the existing grid + pagination

### 3. Update resources/js/Components/GameCard.vue
Add optional props:
- `showDiscount` (boolean): if true, show "-XX%" badge in red
- `showReleaseDate` (boolean): if true, show release date

Make the card show the LOWEST real price only (filter products where is_real_price is true).

## Important
- Do NOT break existing functionality
- Keep dark theme
- All text in Spanish
- Make horizontal sections scrollable with CSS overflow-x-auto
- Responsive design
- Only modify the files mentioned above
