# Task 30: Arquitectura inteligente de actualización de precios

## Context
GamePrice Laravel 12 + Vue 3 + Inertia + Docker + Dokploy.
Production: https://baratoya.billytech.es

## Problema actual
- El botón "Actualizar precios" funciona pero requiere acción manual del usuario
- Los precios pueden estar desactualizados hasta que alguien hace clic
- No hay estrategia para balancear frescura de datos vs no saturar las tiendas
- Los scrapers ejecutan todos a la vez sin throttling

## Arquitectura propuesta: "Smart Refresh Tier System"

### Nivel 1: Cache inteligente con TTL (ya parcialmente implementado)
- Tabla `products` ya tiene timestamps (created_at/updated_at)
- Al cargar una ficha de juego, mostrar precios cacheados inmediatamente
- Si el precio tiene >TTL hours, despachar job en background para actualizar
- TTL configurable por entorno (ej: 24h producción, 1h desarrollo)

### Nivel 2: On-demand para juegos sin precios reales
- Si un juego tiene 0 productos con `is_real_price=true`, forzar actualización síncrona o mostrar "Buscando precios..." + actualizar en background
- Cuando se importa un juego nuevo desde Steam, despachar `FetchPricesForGame` inmediatamente

### Nivel 3: Scheduler periódico para juegos populares
- Laravel Scheduler (`app/Console/Kernel.php`) ejecuta comando cada 6h
- Solo actualiza juegos "activos": con visitas recientes, trending, o con precios reales que expiran
- Limita a N juegos por ejecución (ej: 50 juegos cada 6h = 200 juegos/día)
- Prioriza juegos con precios más antiguos

### Nivel 4: Throttling y rate limiting entre scrapers
- Delay de 200-500ms entre cada scraper dentro del loop
- No ejecutar más de 3 scrapers HTTP simultáneos (CheapShark es API, no cuenta)
- Timeout por scraper: 10s máximo
- Si un scraper falla 3 veces seguidas, desactivarlo temporalmente

### Nivel 5: Fallback graceful
- Si el scraper falla, mantener el último precio conocido
- Añadir campo `price_fetched_at` a tabla `products` para saber antigüedad
- Mostrar indicador visual si precio tiene >48h: "⚠ Precio puede estar desactualizado"

## Cambios necesarios

### 1. Migración: añadir `price_fetched_at` a tabla products
### 2. Modificar `FetchPricesForGame`:
   - Añadir delays entre scrapers (`usleep(200_000)` ya existe, aumentar)
   - Añadir `price_fetched_at` al guardar productos
   - Mejorar manejo de errores (no fallar todo si un scraper falla)
### 3. Modificar `GameController::show()`:
   - Verificar antigüedad de precios al cargar ficha
   - Si precios >TTL, despachar job en background (no bloquear respuesta)
   - Si juego tiene 0 precios reales, mostrar estado de carga
### 4. Crear comando `RefreshStalePrices`:
   - Busca juegos con precios más antiguos que TTL
   - Ordena por antigüedad (más viejos primero)
   - Limita a N juegos por ejecución
   - Ejecuta `FetchPricesForGame` para cada uno
### 5. Configurar Scheduler en `app/Console/Kernel.php`:
   - `schedule->command('prices:refresh-stale')->everySixHours()`
### 6. Frontend: eliminar botón manual (opcional) o mantenerlo como "Forzar actualización"
   - Añadir indicador de última actualización
   - Añadir badge "Precio verificado hace X horas"

## Constraints
- No saturar APIs de tiendas (throttling obligatorio)
- Mantener buena UX (página carga rápido, precios se actualizan en background)
- No perder precios existentes si el scraper falla
- Todo desarrollo vía OpenCode + GLM5.1
- Commits frecuentes

## Verification
- [ ] Al visitar ficha de juego con precios viejos (>24h), se despacha job
- [ ] Job actualiza precios sin crashear
- [ ] Scheduler ejecuta cada 6h y actualiza juegos stale
- [ ] Throttling funciona (delays entre scrapers)
- [ ] Precios nunca desaparecen si el scraper falla
- [ ] Frontend muestra indicador de antigüedad
