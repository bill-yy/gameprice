# Task 20: Completar API Service para RapidAPI

## Contexto
El repositorio ya tiene un `api-service/` — un microservicio Laravel API independiente dentro del monorepo GamePrice. Está diseñado para ser desplegado como servicio separado y monetizado en RapidAPI.

## Estado actual del api-service
- ✅ README completo con docs de RapidAPI
- ✅ Dockerfile, nginx, supervisord
- ✅ Rutas API: `/api/health`, `/api/v1/search`, `/api/v1/prices/{store}`, `/api/v1/stores`, `/api/v1/deals`
- ✅ Middleware: ApiKeyMiddleware, RateLimitMiddleware
- ✅ Controllers: SearchController, StoreController
- ✅ 5 scrapers: Eneba, InstantGaming, CheapShark, G2A, Kinguin
- ❌ **User-Agent antiguo** (Chrome/120, posiblemente GamePriceBot en alguno)
- ❌ **Faltan scrapers**: CDKeys, PSN Store, Xbox Store
- ❌ **No tiene retry logic** ni fallback como el app principal
- ❌ **Scrapers no sincronizados** con los fixes del Task 19

## Objetivo
Sincronizar y completar el api-service para que sea funcional y desplegable.

### 1. Sincronizar User-Agents y fixes de Task 19
En `api-service/app/Services/Scrapers/*.php`:
- Reemplazar todos los User-Agent antiguos (`Chrome/120`, `GamePriceBot`) con `Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36`
- Aumentar timeouts de 5s a 10s donde aplique
- Añadir structured logging (Log::info con http_status, response_size)

### 2. Añadir scrapers faltantes
Copiar/adaptar al `api-service/app/Services/Scrapers/`:
- `CDKeysScraper.php` (del app principal, adaptar namespace)
- `PSNStoreScraper.php` (del app principal, adaptar namespace)
- `XboxStoreScraper.php` (del app principal, adaptar namespace)

Cada uno debe tener:
- `public static function getStoreName(): string`
- `public function search(string $query): ?array`
- `public function searchAll(string $query): array`

### 3. Actualizar SearchController
En `api-service/app/Http/Controllers/Api/V1/SearchController.php`:
- Añadir los 3 scrapers nuevos a `SCRAPERS` constant
- Asegurar que `searchAll` itere sobre todos los scrapers

### 4. Verificar Dockerfile
En `api-service/Dockerfile`:
- Asegurar que builda correctamente
- Revisar si necesita CACHE_BUST para invalidar caché de build

### 5. Añadir composer.json faltante si es necesario
Verificar que `api-service/composer.json` tenga todas las dependencias necesarias (guzzlehttp, illuminate/support, etc.)

### 6. Añadir .env.example completo
Asegurar que `api-service/.env.example` tenga todas las variables necesarias documentadas.

## Archivos a modificar/crear
- `api-service/app/Services/Scrapers/EnebaScraper.php` (fix User-Agent)
- `api-service/app/Services/Scrapers/InstantGamingScraper.php` (fix User-Agent)
- `api-service/app/Services/Scrapers/CheapSharkScraper.php` (fix User-Agent)
- `api-service/app/Services/Scrapers/G2AScraper.php` (fix User-Agent)
- `api-service/app/Services/Scrapers/KinguinScraper.php` (fix User-Agent)
- NUEVO: `api-service/app/Services/Scrapers/CDKeysScraper.php`
- NUEVO: `api-service/app/Services/Scrapers/PSNStoreScraper.php`
- NUEVO: `api-service/app/Services/Scrapers/XboxStoreScraper.php`
- MODIFICAR: `api-service/app/Http/Controllers/Api/V1/SearchController.php`
- REVISAR: `api-service/Dockerfile`
- REVISAR: `api-service/composer.json`

## IMPORTANTE
- Los scrapers del api-service usan `searchAll()` que devuelve array de TODOS los resultados (no solo best match)
- El namespace es `App\Services\Scrapers` (mismo que app principal)
- NO romper la estructura existente
- Al adaptar scrapers del app principal, asegurar que tengan ambos métodos: `search()` y `searchAll()`
