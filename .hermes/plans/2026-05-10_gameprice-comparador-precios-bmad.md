# GamePrice.es - Comparador de Precios de Juegos

> **BMAD Method:** Build → Measure → Analyze → Decide  
> **Implementación:** OpenCode CLI + GLM-5.1 + Subagent-driven-development  
> **Stack:** Laravel 12 + Vue 3 + Inertia.js + Tailwind CSS 4 + PostgreSQL + Dokploy

---

## 🏷️ META

Construir un comparador de precios de videojuegos 100% automatizado (sin contenido editorial) que genere ingresos pasivos mediante programas de afiliados y publicidad.

**Restricción clave:** Cero contenido manual. Cero blogs. Cero guías. Solo datos de APIs.

---

## 🏗️ BUILD (Mes 1-3)

### Sprint 1: Fundación Técnica (Semanas 1-2)

#### Task 1.1: Crear estructura Laravel 12
**Objective:** Inicializar proyecto Laravel con el stack correcto.

**Files:**
- Create: `composer.json`, `.env`, `artisan`
- Create: `app/Models/Game.php`, `app/Models/Store.php`, `app/Models/Product.php`
- Create: database migrations

**Step 1:** Crear proyecto Laravel
```bash
cd ~/projects/gameprice
composer create-project laravel/laravel . --prefer-dist
```
**Expected:** Directorio Laravel creado con artisan disponible.

**Step 2:** Instalar dependencias frontend
```bash
composer require laravel/breeze --dev
php artisan breeze:install vue --dark
npm install
```
**Expected:** Breeze instalado con Vue 3 + Inertia + Tailwind.

**Step 3:** Configurar PostgreSQL en `.env`
```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=gameprice
DB_USERNAME=gameprice
DB_PASSWORD=secret
```

**Step 4:** Crear migraciones base
```bash
php artisan make:migration create_games_table
php artisan make:migration create_stores_table
php artisan make:migration create_products_table
php artisan make:migration create_price_history_table
```

**Step 5:** Commit
```bash
git init
git add .
git commit -m "build: init Laravel 12 + Vue + Inertia + Tailwind"
```

---

#### Task 1.2: Modelos Eloquent y Migraciones
**Objective:** Definir estructura de base de datos para juegos, tiendas y precios.

**Files:**
- Modify: `database/migrations/2026_05_10_000001_create_games_table.php`
- Modify: `database/migrations/2026_05_10_000002_create_stores_table.php`
- Modify: `database/migrations/2026_05_10_000003_create_products_table.php`
- Modify: `database/migrations/2026_05_10_000004_create_price_history_table.php`
- Create: `app/Models/Game.php`
- Create: `app/Models/Store.php`
- Create: `app/Models/Product.php`
- Create: `app/Models/PriceHistory.php`

**Step 1:** Migración `games`
```php
Schema::create('games', function (Blueprint $table) {
    $table->id();
    $table->string('slug')->unique();
    $table->string('title');
    $table->text('description')->nullable();
    $table->date('release_date')->nullable();
    $table->string('cover_image')->nullable();
    $table->string('steam_app_id')->unique()->nullable();
    $table->json('platforms')->nullable();
    $table->json('genres')->nullable();
    $table->string('developer')->nullable();
    $table->string('publisher')->nullable();
    $table->integer('metacritic_score')->nullable();
    $table->timestamps();
    $table->index('slug');
    $table->index('steam_app_id');
});
```

**Step 2:** Migración `stores`
```php
Schema::create('stores', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('slug')->unique();
    $table->string('website');
    $table->string('logo_url')->nullable();
    $table->string('affiliate_program')->nullable();
    $table->decimal('commission_rate', 5, 2)->nullable();
    $table->boolean('is_official')->default(false);
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```

**Step 3:** Migración `products`
```php
Schema::create('products', function (Blueprint $table) {
    $table->id();
    $table->foreignId('game_id')->constrained()->onDelete('cascade');
    $table->foreignId('store_id')->constrained()->onDelete('cascade');
    $table->enum('type', ['key', 'subscription', 'giftcard'])->default('key');
    $table->string('platform')->nullable();
    $table->string('region')->default('global');
    $table->string('edition')->nullable();
    $table->string('url');
    $table->string('affiliate_url')->nullable();
    $table->decimal('current_price', 10, 2)->nullable();
    $table->decimal('original_price', 10, 2)->nullable();
    $table->integer('discount_percent')->default(0);
    $table->string('currency', 3)->default('EUR');
    $table->boolean('in_stock')->default(true);
    $table->timestamp('updated_at')->useCurrent();
    $table->index(['game_id', 'store_id']);
    $table->index('current_price');
});
```

**Step 4:** Modelo `Game.php`
```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Game extends Model
{
    protected $fillable = [
        'slug', 'title', 'description', 'release_date', 'cover_image',
        'steam_app_id', 'platforms', 'genres', 'developer', 'publisher',
        'metacritic_score'
    ];

    protected $casts = [
        'platforms' => 'array',
        'genres' => 'array',
        'release_date' => 'date',
    ];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
```

**Step 5:** Ejecutar migraciones
```bash
php artisan migrate
```
**Expected:** Tablas creadas en PostgreSQL.

**Step 6:** Commit
```bash
git add . && git commit -m "build: add Game, Store, Product models and migrations"
```

---

#### Task 1.3: Seed datos iniciales de tiendas
**Objective:** Poblar tabla stores con tiendas afiliadas.

**Files:**
- Create: `database/seeders/StoreSeeder.php`

**Step 1:** Crear seeder
```bash
php artisan make:seeder StoreSeeder
```

**Step 2:** Implementar StoreSeeder
```php
namespace Database\Seeders;

use App\Models\Store;
use Illuminate\Database\Seeder;

class StoreSeeder extends Seeder
{
    public function run(): void
    {
        $stores = [
            [
                'name' => 'Eneba',
                'slug' => 'eneba',
                'website' => 'https://www.eneba.com',
                'affiliate_program' => 'eneba',
                'commission_rate' => 5.00,
                'is_official' => false,
            ],
            [
                'name' => 'Instant Gaming',
                'slug' => 'instant-gaming',
                'website' => 'https://www.instant-gaming.com',
                'affiliate_program' => 'instant-gaming',
                'commission_rate' => 5.00,
                'is_official' => false,
            ],
            [
                'name' => 'Kinguin',
                'slug' => 'kinguin',
                'website' => 'https://www.kinguin.net',
                'affiliate_program' => 'kinguin',
                'commission_rate' => 5.00,
                'is_official' => false,
            ],
            [
                'name' => 'Fanatical',
                'slug' => 'fanatical',
                'website' => 'https://www.fanatical.com',
                'affiliate_program' => 'awin',
                'commission_rate' => 5.00,
                'is_official' => true,
            ],
            [
                'name' => 'Green Man Gaming',
                'slug' => 'green-man-gaming',
                'website' => 'https://www.greenmangaming.com',
                'affiliate_program' => 'impact',
                'commission_rate' => 5.00,
                'is_official' => true,
            ],
            [
                'name' => 'Humble Bundle',
                'slug' => 'humble-bundle',
                'website' => 'https://www.humblebundle.com',
                'affiliate_program' => 'impact',
                'commission_rate' => 5.00,
                'is_official' => true,
            ],
        ];

        foreach ($stores as $store) {
            Store::create($store);
        }
    }
}
```

**Step 3:** Ejecutar seeder
```bash
php artisan db:seed --class=StoreSeeder
```

**Step 4:** Commit
```bash
git add . && git commit -m "build: seed initial stores"
```

---

### Sprint 2: Integración APIs (Semanas 3-4)

#### Task 2.1: Servicio SteamAppList
**Objective:** Obtener lista completa de juegos desde Steam Web API.

**Files:**
- Create: `app/Services/Steam/SteamAppListService.php`
- Create: `tests/Unit/Services/SteamAppListServiceTest.php`

**Step 1:** Crear servicio
```php
namespace App\Services\Steam;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SteamAppListService
{
    private const API_URL = 'https://api.steampowered.com/ISteamApps/GetAppList/v2/';

    public function fetchAllApps(): array
    {
        $response = Http::timeout(30)->get(self::API_URL);

        if (!$response->successful()) {
            Log::error('Steam AppList API failed', ['status' => $response->status()]);
            return [];
        }

        return $response->json('applist.apps') ?? [];
    }

    public function fetchAndStorePopular(int $limit = 1000): int
    {
        $apps = $this->fetchAllApps();
        $count = 0;

        foreach (array_slice($apps, 0, $limit) as $app) {
            if ($this->isGame($app['appid'])) {
                \App\Models\Game::updateOrCreate(
                    ['steam_app_id' => (string) $app['appid']],
                    ['slug' => \Illuminate\Support\Str::slug($app['name']),
                     'title' => $app['name']]
                );
                $count++;
            }
        }

        return $count;
    }

    private function isGame(int $appId): bool
    {
        // Steam no distingue juegos de DLCs en AppList
        // Filtramos por appid > 0 y nombre no vacío
        return $appId > 0;
    }
}
```

**Step 2:** Test
```bash
php artisan tinker
>>> $service = new \App\Services\Steam\SteamAppListService();
>>> count($service->fetchAllApps());
```
**Expected:** > 100,000 apps retornados.

**Step 3:** Commit
```bash
git add . && git commit -m "build: add SteamAppList service"
```

---

#### Task 2.2: Servicio SteamStoreDetails
**Objective:** Obtener metadatos (descripción, precio, géneros) de juegos desde Steam Store API.

**Files:**
- Create: `app/Services/Steam/SteamStoreService.php`
- Create: `app/Console/Commands/UpdateSteamGames.php`

**Step 1:** Crear servicio
```php
namespace App\Services\Steam;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SteamStoreService
{
    private const API_URL = 'https://store.steampowered.com/api/appdetails';

    public function fetchGameDetails(string $appId): ?array
    {
        $response = Http::timeout(15)->get(self::API_URL, [
            'appids' => $appId,
            'cc' => 'ES',
            'l' => 'spanish',
        ]);

        if (!$response->successful()) {
            return null;
        }

        $data = $response->json($appId);

        if (!($data['success'] ?? false)) {
            return null;
        }

        return $data['data'];
    }

    public function updateGameFromSteam(\App\Models\Game $game): bool
    {
        if (!$game->steam_app_id) {
            return false;
        }

        $details = $this->fetchGameDetails($game->steam_app_id);

        if (!$details) {
            return false;
        }

        $game->update([
            'title' => $details['name'] ?? $game->title,
            'description' => $details['short_description'] ?? $game->description,
            'release_date' => $this->parseReleaseDate($details['release_date']['date'] ?? null),
            'cover_image' => $details['header_image'] ?? $game->cover_image,
            'platforms' => array_keys(array_filter($details['platforms'] ?? [])),
            'genres' => array_column($details['genres'] ?? [], 'description'),
            'developer' => $details['developers'][0] ?? $game->developer,
            'publisher' => $details['publishers'][0] ?? $game->publisher,
            'metacritic_score' => $details['metacritic']['score'] ?? $game->metacritic_score,
        ]);

        return true;
    }

    private function parseReleaseDate(?string $date): ?\Carbon\Carbon
    {
        if (!$date) return null;
        try {
            return \Carbon\Carbon::parse($date);
        } catch (\Exception $e) {
            return null;
        }
    }
}
```

**Step 2:** Crear comando Artisan
```bash
php artisan make:command UpdateSteamGames
```

**Step 3:** Implementar comando
```php
namespace App\Console\Commands;

use App\Models\Game;
use App\Services\Steam\SteamStoreService;
use Illuminate\Console\Command;

class UpdateSteamGames extends Command
{
    protected $signature = 'steam:update-games {--limit=100}';
    protected $description = 'Update game details from Steam Store API';

    public function handle(SteamStoreService $service): int
    {
        $limit = (int) $this->option('limit');
        $games = Game::whereNull('description')->limit($limit)->get();

        $updated = 0;
        $bar = $this->output->createProgressBar($games->count());

        foreach ($games as $game) {
            if ($service->updateGameFromSteam($game)) {
                $updated++;
            }
            $bar->advance();
            usleep(200000); // Rate limit: 5 req/segundo
        }

        $bar->finish();
        $this->newLine();
        $this->info("Updated {$updated} games.");

        return self::SUCCESS;
    }
}
```

**Step 4:** Probar
```bash
php artisan steam:update-games --limit=10
```
**Expected:** "Updated X games."

**Step 5:** Commit
```bash
git add . && git commit -m "build: add SteamStoreService + update-games command"
```

---

#### Task 2.3: Servicio CheapShark
**Objective:** Obtener precios agregados desde CheapShark API.

**Files:**
- Create: `app/Services/CheapShark/CheapSharkService.php`
- Create: `app/Console/Commands/UpdateCheapSharkPrices.php`

**Step 1:** Crear servicio
```php
namespace App\Services\CheapShark;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CheapSharkService
{
    private const API_URL = 'https://www.cheapshark.com/api/1.0';

    public function getDeals(array $params = []): array
    {
        $response = Http::timeout(30)->get(self::API_URL . '/deals', array_merge([
            'pageSize' => 60,
            'sortBy' => 'DealRating',
        ], $params));

        if (!$response->successful()) {
            Log::error('CheapShark API failed', ['status' => $response->status()]);
            return [];
        }

        return $response->json() ?? [];
    }

    public function getDealById(string $dealId): ?array
    {
        $response = Http::timeout(15)->get(self::API_URL . '/deals', ['id' => $dealId]);
        return $response->successful() ? $response->json() : null;
    }

    public function getStores(): array
    {
        $response = Http::timeout(15)->get(self::API_URL . '/stores');
        return $response->successful() ? ($response->json() ?? []) : [];
    }
}
```

**Step 2:** Commit
```bash
git add . && git commit -m "build: add CheapSharkService"
```

---

### Sprint 3: Frontend & Comparador (Semanas 5-6)

#### Task 3.1: Layout base y Home
**Objective:** Crear página principal con buscador y listado de juegos.

**Files:**
- Modify: `resources/js/Pages/Home.vue`
- Create: `resources/js/Components/GameCard.vue`
- Create: `resources/js/Components/SearchBar.vue`

**Step 1:** Home.vue
```vue
<template>
  <div class="min-h-screen bg-gray-900 text-white">
    <header class="bg-gray-800 border-b border-gray-700">
      <div class="max-w-7xl mx-auto px-4 py-6">
        <h1 class="text-3xl font-bold text-center mb-4">GamePrice.es</h1>
        <SearchBar v-model="search" @search="handleSearch" />
      </div>
    </header>

    <main class="max-w-7xl mx-auto px-4 py-8">
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
        <GameCard v-for="game in games.data" :key="game.id" :game="game" />
      </div>

      <div v-if="games.data.length === 0" class="text-center py-20 text-gray-400">
        No se encontraron juegos.
      </div>
    </main>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { router } from '@inertiajs/vue3'
import SearchBar from '@/Components/SearchBar.vue'
import GameCard from '@/Components/GameCard.vue'

const props = defineProps({
  games: Object,
  filters: Object,
})

const search = ref(props.filters?.search || '')

const handleSearch = () => {
  router.get('/', { search: search.value }, { preserveState: true })
}
</script>
```

**Step 2:** GameCard.vue
```vue
<template>
  <Link :href="`/juego/${game.slug}`" class="block bg-gray-800 rounded-lg overflow-hidden hover:ring-2 hover:ring-blue-500 transition">
    <img :src="game.cover_image || '/img/game-placeholder.jpg'" :alt="game.title" class="w-full h-40 object-cover" loading="lazy" />
    <div class="p-4">
      <h3 class="font-semibold text-lg truncate">{{ game.title }}</h3>
      <p class="text-sm text-gray-400">{{ game.developer }}</p>
      <div class="mt-2 flex items-center justify-between">
        <span v-if="lowestPrice" class="text-green-400 font-bold">desde {{ lowestPrice }}€</span>
        <span v-else class="text-gray-500">Sin precios</span>
        <span v-if="maxDiscount" class="bg-red-600 text-xs px-2 py-1 rounded">-{{ maxDiscount }}%</span>
      </div>
    </div>
  </Link>
</template>

<script setup>
import { Link } from '@inertiajs/vue3'
import { computed } from 'vue'

const props = defineProps({
  game: Object,
})

const lowestPrice = computed(() => {
  const prices = props.game.products?.map(p => p.current_price).filter(Boolean)
  return prices?.length ? Math.min(...prices).toFixed(2) : null
})

const maxDiscount = computed(() => {
  const discounts = props.game.products?.map(p => p.discount_percent).filter(Boolean)
  return discounts?.length ? Math.max(...discounts) : null
})
</script>
```

**Step 3:** Commit
```bash
git add . && git commit -m "build: add Home page + GameCard + SearchBar components"
```

---

#### Task 3.2: Página de juego con comparador
**Objective:** Ficha de juego con comparación de precios por tienda.

**Files:**
- Create: `resources/js/Pages/GameShow.vue`
- Create: `app/Http/Controllers/GameController.php`
- Modify: `routes/web.php`

**Step 1:** GameController.php
```php
namespace App\Http\Controllers;

use App\Models\Game;
use Illuminate\Http\Request;
use Inertia\Inertia;

class GameController extends Controller
{
    public function index(Request $request)
    {
        $games = Game::with(['products.store'])
            ->when($request->search, function ($query, $search) {
                $query->where('title', 'ilike', "%{$search}%");
            })
            ->orderBy('metacritic_score', 'desc')
            ->paginate(24);

        return Inertia::render('Home', [
            'games' => $games,
            'filters' => $request->only('search'),
        ]);
    }

    public function show(Game $game)
    {
        $game->load(['products.store' => function ($query) {
            $query->where('is_active', true);
        }]);

        $products = $game->products->sortBy('current_price')->values();

        return Inertia::render('GameShow', [
            'game' => $game,
            'products' => $products,
        ]);
    }
}
```

**Step 2:** GameShow.vue
```vue
<template>
  <div class="min-h-screen bg-gray-900 text-white">
    <div class="max-w-7xl mx-auto px-4 py-8">
      <div class="flex flex-col md:flex-row gap-8">
        <div class="md:w-1/3">
          <img :src="game.cover_image" :alt="game.title" class="w-full rounded-lg shadow-lg" />
        </div>
        <div class="md:w-2/3">
          <h1 class="text-4xl font-bold mb-2">{{ game.title }}</h1>
          <p class="text-gray-400 mb-4">{{ game.developer }} | {{ game.release_date }}</p>
          <p class="text-gray-300 mb-6">{{ game.description }}</p>

          <div class="flex gap-2 mb-6">
            <span v-for="genre in game.genres" :key="genre" class="bg-gray-700 px-3 py-1 rounded-full text-sm">{{ genre }}</span>
          </div>
        </div>
      </div>

      <div class="mt-12">
        <h2 class="text-2xl font-bold mb-6">Comparar precios</h2>
        <div class="bg-gray-800 rounded-lg overflow-hidden">
          <table class="w-full">
            <thead class="bg-gray-700">
              <tr>
                <th class="text-left p-4">Tienda</th>
                <th class="text-left p-4">Plataforma</th>
                <th class="text-left p-4">Región</th>
                <th class="text-right p-4">Precio</th>
                <th class="text-right p-4">Descuento</th>
                <th class="p-4"></th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="product in products" :key="product.id" class="border-t border-gray-700 hover:bg-gray-750">
                <td class="p-4 flex items-center gap-2">
                  <img :src="product.store.logo_url" class="w-6 h-6" v-if="product.store.logo_url" />
                  {{ product.store.name }}
                </td>
                <td class="p-4">{{ product.platform || 'PC' }}</td>
                <td class="p-4">{{ product.region }}</td>
                <td class="p-4 text-right font-bold text-green-400">{{ product.current_price }}€</td>
                <td class="p-4 text-right">
                  <span v-if="product.discount_percent > 0" class="text-red-400">-{{ product.discount_percent }}%</span>
                </td>
                <td class="p-4">
                  <a :href="product.affiliate_url || product.url" target="_blank" rel="nofollow"
                     class="bg-blue-600 hover:bg-blue-500 px-4 py-2 rounded text-sm font-semibold transition">
                    Ver oferta
                  </a>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
const props = defineProps({
  game: Object,
  products: Array,
})
</script>
```

**Step 3:** Actualizar rutas
```php
use App\Http\Controllers\GameController;

Route::get('/', [GameController::class, 'index'])->name('home');
Route::get('/juego/{game}', [GameController::class, 'show'])->name('game.show');
```

**Step 4:** Commit
```bash
git add . && git commit -m "build: add GameShow page with price comparison table"
```

---

#### Task 3.3: SEO automático
**Objective:** Meta tags y Schema.org generados dinámicamente desde datos.

**Files:**
- Modify: `resources/js/Pages/GameShow.vue`
- Create: `app/Helpers/SeoHelper.php`

**Step 1:** SeoHelper.php
```php
namespace App\Helpers;

use App\Models\Game;

class SeoHelper
{
    public static function forGame(Game $game): array
    {
        $lowestPrice = $game->products->whereNotNull('current_price')->min('current_price');
        $maxDiscount = $game->products->max('discount_percent');

        $title = "Comprar {$game->title} barato - Comparador de precios PC";
        $description = "Encuentra {$game->title} al mejor precio. ";
        $description .= $lowestPrice ? "Desde {$lowestPrice}€. " : "";
        $description .= $maxDiscount ? "Descuentos de hasta {$maxDiscount}%. " : "";
        $description .= "Compara ofertas de " . $game->products->count() . " tiendas.";

        return [
            'title' => $title,
            'description' => $description,
            'schema' => [
                '@context' => 'https://schema.org',
                '@type' => 'Product',
                'name' => $game->title,
                'image' => $game->cover_image,
                'description' => $game->description,
                'brand' => [
                    '@type' => 'Brand',
                    'name' => $game->developer ?? 'Unknown',
                ],
                'offers' => $game->products->map(fn ($p) => [
                    '@type' => 'Offer',
                    'price' => $p->current_price,
                    'priceCurrency' => $p->currency,
                    'availability' => $p->in_stock ? 'https://schema.org/InStock' : 'https://schema.org/OutOfStock',
                    'seller' => [
                        '@type' => 'Organization',
                        'name' => $p->store->name,
                    ],
                ])->toArray(),
            ],
        ];
    }
}
```

**Step 2:** Commit
```bash
git add . && git commit -m "build: add automatic SEO helper for games"
```

---

### Sprint 4: Automatización & Deploy (Semanas 7-8)

#### Task 4.1: Cron jobs y actualización automática
**Objective:** Scheduler Laravel para actualizar precios cada 6 horas.

**Files:**
- Modify: `routes/console.php`
- Create: `app/Console/Commands/UpdateAllPrices.php`
- Create: `app/Console/Commands/UpdatePriceHistory.php`

**Step 1:** UpdateAllPrices command
```bash
php artisan make:command UpdateAllPrices
```

**Step 2:** Implementar
```php
namespace App\Console\Commands;

use App\Models\Game;
use App\Models\Product;
use App\Services\CheapShark\CheapSharkService;
use Illuminate\Console\Command;

class UpdateAllPrices extends Command
{
    protected $signature = 'prices:update';
    protected $description = 'Update all product prices from APIs';

    public function handle(CheapSharkService $cheapShark): int
    {
        $this->info('Updating prices from CheapShark...');

        $deals = $cheapShark->getDeals(['pageSize' => 60]);

        foreach ($deals as $deal) {
            $game = Game::where('title', $deal['title'])->first();

            if (!$game) continue;

            $store = \App\Models\Store::where('slug', $this->mapStore($deal['storeID']))->first();

            if (!$store) continue;

            Product::updateOrCreate(
                ['game_id' => $game->id, 'store_id' => $store->id],
                [
                    'current_price' => $deal['salePrice'],
                    'original_price' => $deal['normalPrice'],
                    'discount_percent' => round($deal['savings']),
                    'currency' => 'USD',
                    'in_stock' => true,
                    'url' => $deal['dealID'] ? "https://www.cheapshark.com/redirect?dealID={$deal['dealID']}" : null,
                ]
            );
        }

        $this->info('Prices updated.');
        return self::SUCCESS;
    }

    private function mapStore(string $storeId): string
    {
        $map = [
            '1' => 'steam',
            '7' => 'gog',
            '11' => 'humble-bundle',
            '21' => 'win-game-store',
            '23' => 'game-billet',
            '24' => 'fanatical',
            '25' => 'green-man-gaming',
            '29' => 'eneba',
        ];

        return $map[$storeId] ?? 'unknown';
    }
}
```

**Step 3:** Scheduler
```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('steam:update-games --limit=50')->everySixHours();
Schedule::command('prices:update')->everySixHours();
Schedule::command('prices:history')->daily();
```

**Step 4:** Commit
```bash
git add . && git commit -m "build: add automated price update cron jobs"
```

---

#### Task 4.2: Docker + Dokploy
**Objective:** Contenedorizar para deploy en Dokploy.

**Files:**
- Create: `Dockerfile`
- Create: `docker-compose.yml`
- Create: `docker/nginx/default.conf`
- Create: `docker/php/uploads.ini`

**Step 1:** Dockerfile
```dockerfile
FROM dunglas/frankenphp:latest-php8.4

RUN install-php-extensions pdo_pgsql pgsql intl zip gd redis

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-scripts --no-autoloader --no-dev

COPY . .
RUN composer dump-autoload --optimize

RUN php artisan config:cache
RUN php artisan route:cache

COPY docker/php/uploads.ini /usr/local/etc/php/conf.d/uploads.ini

EXPOSE 80

CMD ["frankenphp", "run", "--config", "/app/Caddyfile"]
```

**Step 2:** docker-compose.yml
```yaml
version: '3.8'

services:
  app:
    build: .
    ports:
      - "80:80"
    environment:
      - APP_ENV=production
      - APP_KEY=${APP_KEY}
      - DB_CONNECTION=pgsql
      - DB_HOST=db
      - DB_DATABASE=gameprice
      - DB_USERNAME=gameprice
      - DB_PASSWORD=${DB_PASSWORD}
    depends_on:
      - db
      - redis

  db:
    image: postgres:16-alpine
    environment:
      - POSTGRES_DB=gameprice
      - POSTGRES_USER=gameprice
      - POSTGRES_PASSWORD=${DB_PASSWORD}
    volumes:
      - pgdata:/var/lib/postgresql/data

  redis:
    image: redis:7-alpine

  queue:
    build: .
    command: php artisan queue:work --sleep=3 --tries=3
    environment:
      - APP_ENV=production
      - APP_KEY=${APP_KEY}
    depends_on:
      - db
      - redis

volumes:
  pgdata:
```

**Step 3:** uploads.ini
```ini
upload_max_filesize=100M
post_max_size=100M
max_execution_time=300
memory_limit=512M
```

**Step 4:** Commit
```bash
git add . && git commit -m "build: add Docker + Dokploy config"
```

---

#### Task 4.3: Registro afiliados y Google AdSense
**Objective:** Preparar monetización.

**Files:**
- Create: `docs/AFFILIATES.md`
- Create: `resources/js/Components/AdBanner.vue`

**Step 1:** AFFILIATES.md (checklist)
```markdown
# Programas de Afiliados

## Registrados
- [ ] Eneba (https://www.eneba.com/become-affiliate)
- [ ] Instant Gaming (contacto directo)
- [ ] Kinguin (https://affiliate.kinguin.net)
- [ ] Fanatical (affiliates@fanatical.com)
- [ ] Green Man Gaming (Impact)
- [ ] Humble Bundle (Impact)

## Implementación
- [ ] Añadir affiliate_id a URLs de redirección
- [ ] Trackear clics en analytics
- [ ] Rotar tiendas destacadas
```

**Step 2:** Commit
```bash
git add . && git commit -m "build: add affiliate programs checklist + AdSense placeholder"
```

---

## 📈 MEASURE (Mes 3)

### KPIs a medir

| Métrica | Target Mes 3 | Cómo medir |
|---------|-------------|-----------|
| Juegos indexados | 1,000+ | `Game::count()` |
| Precios activos | 500+ | `Product::whereNotNull('current_price')->count()` |
| Páginas indexadas Google | 100+ | Search Console |
| Tráfico orgánico | 500+ visitas/mes | Google Analytics |
| Tiempo de carga | < 2s | Lighthouse |
| Uptime | 99%+ | Dokploy monitoring |

### Tareas

#### Task M.1: Google Search Console + Analytics
- Verificar dominio
- Enviar sitemap XML (`/sitemap.xml`)
- Configurar eventos: clic en "Ver oferta", búsqueda

#### Task M.2: Sitemap auto-generado
**Files:**
- Create: `app/Http/Controllers/SitemapController.php`
- Modify: `routes/web.php`

```php
public function index()
{
    $games = Game::select('slug', 'updated_at')->get();
    return response()->view('sitemap', compact('games'))->header('Content-Type', 'text/xml');
}
```

#### Task M.3: Lighthouse CI
```bash
npm install -g @lhci/cli
lhci autorun
```

---

## 🔍 ANALYZE (Mes 4-6)

### Qué analizar

1. **Qué juegos generan más tráfico** → Priorizar actualización de precios
2. **Qué tiendas converten más** → Optimizar posición en tabla
3. **CTR de "Ver oferta"** → A/B test de color/posición del botón
4. **Bounce rate** → Si > 70%, mejorar SEO/velocidad

### Tareas

#### Task A.1: Analytics de conversiones
- Trackear clic en affiliate links
- Guardar en DB: `affiliate_clicks` table

#### Task A.2: Optimización de rendimiento
- Cache Redis para precios (TTL: 1h)
- Lazy loading de imágenes
- CDN para assets estáticos

#### Task A.3: A/B testing botón "Ver oferta"
- Variante A: Azul (actual)
- Variante B: Naranja/Verde
- Medir CTR durante 2 semanas

---

## 📊 DECIDE (Mes 6+)

### Decisiones basadas en datos

| Escenario | decisión |
|-----------|---------|
| Tráfico > 10K/mes y > €50/mes | Escalar: añadir más tiendas, optimizar SEO |
| Tráfico < 1K/mes | Pivotar: cambiar nicho (ej. suscripciones exclusivo) |
| CTR affiliate < 1% | Cambiar UX: resaltar mejor precio, reviews de tiendas |
| Ads RPM < $2 | Esperar a más tráfico antes de optimizar ads |

### Tareas

#### Task D.1: Decisión formal
- Revisar métricas a mes 6
- Documentar decisión en `docs/DECISION-6M.md`
- Ajustar roadmap según resultado

---

## 🤖 IMPLEMENTACIÓN CON OPENCODE + GLM-5.1

### Flujo de trabajo

1. **Crear plan** (este documento) ✓
2. **Delegar tareas** a subagentes OpenCode con GLM-5.1
3. **Cada tarea** = un `opencode run --model zai-coding-plan/glm-5.1 "[task description]"`
4. **Review** manual de cada PR/commit
5. **Medir** resultados tras cada sprint

### Comandos OpenCode

```bash
export PATH="$HOME/.local/opencode/node_modules/opencode-ai/bin:$PATH"

# Task 1.1
opencode run --model zai-coding-plan/glm-5.1 "Create Laravel 12 project with Vue 3, Inertia, Tailwind, PostgreSQL. Initialize git."

# Task 1.2
opencode run --model zai-coding-plan/glm-5.1 "Create Game, Store, Product Eloquent models with migrations for a game price comparison site."

# Task 2.1
opencode run --model zai-coding-plan/glm-5.1 "Create SteamAppListService that fetches all games from Steam API and stores them in the database."
```

---

## 📅 TIMELINE

| Sprint | Semanas | Tareas | Entregable |
|--------|---------|--------|-----------|
| 1 | 1-2 | 1.1 - 1.3 | Laravel + DB + stores seed |
| 2 | 3-4 | 2.1 - 2.3 | APIs integradas (Steam + CheapShark) |
| 3 | 5-6 | 3.1 - 3.3 | Frontend completo (Home + GameShow) |
| 4 | 7-8 | 4.1 - 4.3 | Cron jobs + Docker + afiliados |
| MEASURE | 9-12 | M.1 - M.3 | Métricas base, sitemap, SEO |
| ANALYZE | 13-18 | A.1 - A.3 | Optimizaciones basadas en datos |
| DECIDE | 19-24 | D.1 | Decisión: escalar o pivotar |

---

## 💸 COSTOS

| Rubro | Costo/mes |
|-------|----------|
| VPS (Dokploy) | €10-15 |
| Dominio | €1 |
| APIs | €0 (todas gratuitas) |
| **Total** | **€11-16** |

---

## ✅ CHECKLIST BMAD

### BUILD
- [ ] Laravel 12 + Vue 3 + Inertia + Tailwind ✓
- [ ] PostgreSQL + migraciones ✓
- [ ] Steam API integrada ✓
- [ ] CheapShark API integrada ✓
- [ ] Frontend Home + GameShow ✓
- [ ] Cron jobs automáticos ✓
- [ ] Docker + Dokploy ✓

### MEASURE
- [ ] Google Search Console
- [ ] Google Analytics 4
- [ ] Sitemap XML
- [ ] Lighthouse CI

### ANALYZE
- [ ] CTR affiliate links
- [ ] Bounce rate por página
- [ ] Tiendas más convertidoras
- [ ] Velocidad de carga

### DECIDE
- [ ] Revisión mes 3
- [ ] Revisión mes 6
- [ ] Roadmap ajustado

---

> **Próximo paso:** Ejecutar Sprint 1 con OpenCode + GLM-5.1
