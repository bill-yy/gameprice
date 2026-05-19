# Task 22: Hacer API lista para producción y RapidAPI

## Contexto
La API integrada en GamePrice funciona pero tiene problemas críticos que la hacen NO apta para producción/RapidAPI.

## Problemas identificados

### 1. Autenticación rota (CRÍTICO)
Las rutas `/api/v1/*` devuelven 200 incluso sin `X-API-Key`. El middleware no está protegiendo correctamente.

**Fix:** En `routes/api.php`, las rutas v1 deben estar DENTRO de un grupo con middleware `api` o asegurar que `ApiKeyMiddleware` se ejecute. Revisar `bootstrap/app.php`:
```php
$middleware->api(prepend: [
    \App\Http\Middleware\ApiKeyMiddleware::class,
    \App\Http\Middleware\RateLimitMiddleware::class,
]);
```
Esto aplica a TODAS las rutas api, pero `/api/health` debería estar EXCLUIDO.

**Solución:** Usar `Route::withoutMiddleware()` en `/api/health` o mover health a `routes/web.php`.

### 2. Deals endpoint devuelve 500 (CRÍTICO)
`GET /api/v1/deals` retorna `{"success":false,"error":"Failed to fetch deals"}`.

**Fix:** El `SearchController::deals()` hace request a CheapShark pero falla. Añadir mejor logging y fallback.

### 3. Search all tarda 12.7s (CRÍTICO para UX)
Buscar en 8 scrapers secuencialmente es lento.

**Fix:** Ejecutar scrapers en paralelo usando `Http::pool()` o `Promise` de Guzzle. Laravel Http facade soporta concurrent requests:
```php
$responses = Http::pool(fn ($pool) => [
    $pool->as('eneba')->withHeaders(...)->timeout(5)->get($enebaUrl),
    $pool->as('ig')->withHeaders(...)->timeout(5)->get($igUrl),
    // ...
]);
```
Con timeout de 5s por scraper + paralelismo, el tiempo total debería ser < 6s en el peor caso.

### 4. Filtrar productos con precio 0
Algunos scrapers devuelven precio 0 cuando no encuentran datos.

**Fix:** En `SearchController::searchAll()`, filtrar resultados:
```php
$allResults = array_filter($allResults, fn ($r) => ($r['price'] ?? 0) > 0);
```

### 5. Rate limiting robusto
El rate limit actual usa caché en archivo (`file` driver). En producción con múltiples réplicas, usar Redis.

**Fix:** Verificar que `CACHE_STORE` esté configurado correctamente en producción. Añadir fallback si Redis no está disponible.

## Qué construir

### Archivos a modificar
1. `routes/api.php` — Excluir `/api/health` del middleware api, o mover health
2. `app/Http/Controllers/Api/V1/SearchController.php` —
   - Fix deals endpoint con mejor error handling
   - Implementar búsqueda paralela con `Http::pool()`
   - Filtrar precios = 0
3. `app/Http/Middleware/ApiKeyMiddleware.php` — Asegurar que funcione correctamente
4. `app/Http/Middleware/RateLimitMiddleware.php` — Mejorar con Redis fallback
5. `bootstrap/app.php` — Revisar configuración de middleware groups

### Testing post-fix
- `curl /api/v1/stores` sin header → debe dar **401**
- `curl /api/health` sin header → debe dar **200**
- `curl /api/v1/search?q=elden+ring` con key → debe completar en **< 6s**
- `curl /api/v1/deals` con key → debe dar **200** con datos
- Ningún producto con precio = 0

## IMPORTANTE
- NO romper rutas web existentes (Inertia SPA)
- Usar OpenCode + GLM5.1 para implementación
- Commit y push después de fix
