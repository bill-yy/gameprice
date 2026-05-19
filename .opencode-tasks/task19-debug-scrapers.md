# Task 19: Diagnóstico y fix de scrapers + retry logic

## Contexto
GamePrice es un comparador de precios Laravel 12 + Vue 3. Tenemos 8 scrapers de precios reales en `app/Services/Scrapers/`:

1. CheapSharkScraper - API REST (más confiable)
2. EnebaScraper - HTML scraping
3. InstantGamingScraper - HTML scraping
4. G2AScraper - API
5. KinguinScraper - API
6. CDKeysScraper - HTML scraping
7. PSNStoreScraper - API oficial + fallback HTML
8. XboxStoreScraper - API oficial + fallback HTML

## Problema
Algunos juegos muestran precios reales (Elden Ring, Tour de France 2025) pero otros NO (Call of Duty: MW2 dice "No hay precios reales disponibles"). Los scrapers pueden fallar silenciosamente por:
- WAF/bloqueo de IPs
- Timeouts
- Cambios en estructura HTML de las tiendas
- APIs que devuelven 403/429

## Objetivo
Implementar un sistema de diagnóstico + mejora de robustez:

### 1. Crear DebugScraperService
Crear `app/Services/DebugScraperService.php` que:
- Reciba un game_title
- Ejecute TODOS los scrapers uno por uno
- Devuelva un array con resultado de cada uno:
  ```php
  [
    'cheapshark' => ['success' => true, 'price' => 29.99, 'error' => null, 'elapsed_ms' => 1200],
    'eneba' => ['success' => false, 'price' => null, 'error' => 'HTTP 403', 'elapsed_ms' => 500],
    ...
  ]
  ```
- Mida tiempo de ejecución de cada scraper

### 2. Crear endpoint de debug
En `routes/web.php` (solo local, protegido con `APP_ENV=local` o similar):
```
GET /debug/scraper/{game}
```
Que use DebugScraperService y devuelva JSON con resultados detallados.

### 3. Mejorar FetchPricesForGame job
En `app/Jobs/FetchPricesForGame.php`:
- Agregar retry logic: si un scraper falla, reintentar 1 vez con delay de 2s
- Agregar timeout por scraper individual (máx 15s por scraper)
- NO detener todo el job si un scraper falla (ya lo hace, pero verificar)
- Agregar métrica: contar cuántos scrapers fallaron vs éxito

### 4. Mejorar logging
En cada scraper, agregar log estructurado:
```php
Log::info("Scraper {$slug}: result", [
    'game' => $gameTitle,
    'success' => $result !== null,
    'price' => $result['price_eur'] ?? null,
    'http_status' => $response->status(),
    'response_size' => strlen($response->body()),
]);
```

### 5. Verificar/fix scrapers individuales
Revisar cada scraper y asegurar que:
- Usa User-Agent real de navegador (no GamePriceBot que puede ser bloqueado)
- Maneja correctamente 403/429/500
- No falla silenciosamente

### 6. Agregar fallback de precios
Si después de ejecutar todos los scrapers NO hay ningún precio real, agregar una lógica que:
- Busque en caché el último precio conocido para ese juego
- O devuelva un mensaje claro de "precios no disponibles temporalmente"

## Archivos a modificar/crear
- NUEVO: `app/Services/DebugScraperService.php`
- MODIFICAR: `app/Jobs/FetchPricesForGame.php` (retry logic, timeouts, métricas)
- MODIFICAR: `app/Services/Scrapers/*.php` (mejorar logging, user agents)
- MODIFICAR: `routes/web.php` (endpoint debug, solo si no existe ya)
- Verificar: `app/Http/Controllers/GameController.php` (el método testScraper ya existe, revisar si sirve)

## IMPORTANTE
- CheapShark es la API más confiable, asegurar que siempre se intenta primero
- Los HTML scrapers (Eneba, Instant Gaming, CDKeys) son más propensos a fallar
- Los scrapers de consola (PSN, Xbox) solo funcionan para juegos de consola
- No romper funcionalidad existente
