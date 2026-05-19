# Task 21: Integrar API de scraping en app principal de GamePrice

## Contexto
El api-service (`api-service/`) es un microservicio Laravel separado dentro del monorepo. Los builds en Dokploy fallan silenciosamente para aplicaciones nuevas. 

La solución pragmática es integrar los endpoints del API directamente en la app principal de GamePrice (`/`), que ya se despliega correctamente en Dokploy. Así el API está "junto con GamePrice" y comparte infraestructura.

## Objetivo
Añadir endpoints API a la app principal que expongan los scrapers como servicio REST, listo para RapidAPI.

## Qué existe
- App principal: Laravel 12 + Vue 3 + Inertia, ya desplegada en baratoya.billytech.es
- 8 scrapers en `app/Services/Scrapers/`: Eneba, InstantGaming, CheapShark, G2A, Kinguin, CDKeys, PSNStore, XboxStore
- Cada scraper tiene `search()` y `searchAll()`
- Rutas actuales en `routes/web.php` (Inertia SPA)
- `config/app.php` ya configurado

## Qué construir

### 1. Crear `routes/api.php`
Crear archivo `routes/api.php` con:
```php
<?php
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\SearchController;
use App\Http\Controllers\Api\V1\StoreController;

Route::get('/health', fn() => response()->json(['status' => 'ok']));

Route::prefix('v1')->group(function () {
    Route::get('/search', [SearchController::class, 'searchAll']);
    Route::get('/prices/{store}', [SearchController::class, 'searchByStore']);
    Route::get('/stores', [StoreController::class, 'index']);
    Route::get('/deals', [SearchController::class, 'deals']);
});
```

### 2. Crear controllers en app principal
Copiar/adaptar de `api-service/app/Http/Controllers/Api/V1/`:
- `SearchController.php` → `app/Http/Controllers/Api/V1/SearchController.php`
- `StoreController.php` → `app/Http/Controllers/Api/V1/StoreController.php`
- `Controller.php` → `app/Http/Controllers/Api/V1/Controller.php`

Adaptar namespaces para usar los scrapers de `app/Services/Scrapers/` (ya existen en app principal).

### 3. Crear middleware API
Copiar de `api-service/app/Http/Middleware/`:
- `ApiKeyMiddleware.php`
- `RateLimitMiddleware.php`

Registrar en `bootstrap/app.php`:
```php
->withMiddleware(function (Middleware $middleware) {
    $middleware->api(prepend: [
        \App\Http\Middleware\ApiKeyMiddleware::class,
        \App\Http\Middleware\RateLimitMiddleware::class,
    ]);
})
```

### 4. Configuración API
Añadir a `config/api.php` (crear si no existe):
```php
<?php
return [
    'rate_limit' => env('API_RATE_LIMIT', 100),
    'keys' => explode(',', env('API_KEYS', 'dev-key-change-me-in-production')),
];
```

### 5. Asegurar rutas API funcionan
En `bootstrap/app.php` verificar que `withRouting` incluya `api:`:
```php
->withRouting(
    web: __DIR__.'/../routes/web.php',
    api: __DIR__.'/../routes/api.php',
    apiPrefix: 'api',
    commands: __DIR__.'/../routes/console.php',
    health: '/up',
)
```

## Endpoints resultantes
- `GET /api/health` — Sin auth
- `GET /api/v1/search?q=elden+ring` — Busca en 8 tiendas
- `GET /api/v1/prices/{store}?q=...` — Busca en tienda específica
- `GET /api/v1/stores` — Lista tiendas
- `GET /api/v1/deals` — Mejores ofertas

## IMPORTANTE
- NO modificar rutas web existentes (Inertia SPA)
- Los scrapers ya existen en app principal, reutilizarlos
- Añadir env vars `API_RATE_LIMIT` y `API_KEYS` al `.env.example`
- Commit y push para deploy automático en Dokploy
