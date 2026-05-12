# 🎮 GamePrice - Plan Completo de Producción
## Análisis de competencia (AllKeyShop) + Estrategia de Tiendas + Funcionalidades Core

---

## 1. ANÁLISIS DE TIENDAS (Benchmark AllKeyShop)

AllKeyShop lista **52 tiendas** activas. Análisis de cuáles podemos integrar:

### ✅ TIENDAS YA INTEGRADAS (7/52)
| Tienda | Tipo | Estado | Fuente |
|--------|------|--------|--------|
| Steam | Oficial | ✅ Real | CheapShark |
| Eneba | Grey Market | ✅ Real | Apollo GraphQL scraper |
| Instant Gaming | Grey Market | ⚪ Estimado | Playwright (GitHub Actions) |
| Kinguin | Grey Market | ⚪ Estimado | Playwright (GitHub Actions) |
| Fanatical | Oficial | ✅ Real | CheapShark |
| GreenManGaming | Oficial | ✅ Real | CheapShark |
| Humble Bundle | Oficial | ✅ Real | CheapShark |
| GOG | Oficial | ✅ Real | CheapShark |
| Epic Games Store | Oficial | ✅ Real | CheapShark |

### 🔍 TIENDAS CANDIDATAS PARA AÑADIR (priorizadas por scrapeabilidad)

#### TIER 1: APIs públicas / JSON embebido (Fácil)
| Tienda | Tipo | Método | Complejidad |
|--------|------|--------|-------------|
| **Gamesplanet** | Oficial | API pública disponible | ⭐⭐ |
| **GamersGate** | Oficial | HTML scrapeable (precios en meta tags) | ⭐⭐ |
| **2Game** | Oficial | HTML con datos estructurados | ⭐⭐ |
| **IndieGala** | Oficial | API de bundles + store | ⭐⭐ |
| **WinGameStore** | Oficial | CheapShark ya lo cubre | ⭐ |
| **GameBillet** | Oficial | CheapShark ya lo cubre | ⭐ |

#### TIER 2: Scraping con HTTP directo (Medio)
| Tienda | Tipo | Método | Complejidad |
|--------|------|--------|-------------|
| **G2A** | Grey Market | API de búsqueda (rate limited) | ⭐⭐⭐ |
| **Gamivo** | Grey Market | HTML + Apollo GraphQL (similar a Eneba) | ⭐⭐⭐ |
| **K4G** | Grey Market | HTML scrapeable | ⭐⭐⭐ |
| **HRK** | Grey Market | HTML scrapeable | ⭐⭐⭐ |
| **Driffle** | Grey Market | HTML scrapeable | ⭐⭐⭐ |
| **Bcdkey** | Grey Market | HTML scrapeable | ⭐⭐ |
| **Keys4us** | Grey Market | HTML scrapeable | ⭐⭐ |
| **Keywrld** | Grey Market | HTML scrapeable | ⭐⭐ |

#### TIER 3: Playwright / Selenium necesario (Difícil)
| Tienda | Tipo | Método | Complejidad |
|--------|------|--------|-------------|
| **G2A** | Grey Market | JS-rendered, requiere browser | ⭐⭐⭐⭐ |
| **Amazon** | Oficial | Bot detection agresivo | ⭐⭐⭐⭐⭐ |
| **Epic Games** | Oficial | API GraphQL (documentada) | ⭐⭐⭐ |
| **Battle.net** | Oficial | No vende keys de terceros | ❌ |
| **Rockstar** | Oficial | No vende keys de terceros | ❌ |

#### TIER 4: Descartadas (No viables)
| Tienda | Razón |
|--------|--------|
| EA.com | No vende keys de terceros |
| Nintendo eShop | No vende keys de PC |
| Xbox | No vende keys de PC |
| Eldorado | Rating 1.81, poco fiable |
| ElectronicFirst | Rating 2.21, poco fiable |
| Indiegala | Rating 1.75, poco fiable |
| Store700 | Rating 1.75, poco fiable |
| Wyrel | Rating 1.73, poco fiable |
| Gamingdragons | Rating 2.79, poco fiable |
| Keycense | Rating 2.84, poco fiable |

---

## 2. PLAN DE IMPLEMENTACIÓN DE NUEVAS TIENDAS

### Fase 1: Tiendas Oficiales (Alto impacto, bajo riesgo)

```
├── Gamesplanet API
│   └── API: https://api.gamesplanet.com (documentada)
│   └── Requiere API key gratuita
│   └── Coverage: Europa principalmente
│
d├── GamersGate
│   └── Scrape: meta tags OG + JSON-LD
│   └── URL: https://www.gamersgate.com/product/{slug}
│
d├── 2Game
│   └── Scrape: HTML con microdata
│   └── URL: https://2game.com/en-us/search?query={game}
│
d└── IndieGala Store
    └── API: https://www.indiegala.com/store_search (AJAX)
    └── Coverage: Muy buena en indies
```

### Fase 2: Grey Market adicionales (Alto impacto, medio riesgo)

```
├── Gamivo
│   └── Método: Apollo GraphQL (igual que Eneba)
│   └── URL: https://www.gamivo.com/search?q={game}
│
d├── K4G
│   └── Método: HTML scrapeable
│   └── URL: https://k4g.com/search?search={game}
│
d├── HRK Game
│   └── Método: HTML scrapeable
│   └── URL: https://www.hrkgame.com/en/search?q={game}
│
d└── Driffle
    └── Método: HTML scrapeable
    └── URL: https://driffle.com/search?q={game}
```

### Fase 3: G2A (Alta complejidad, alto valor)

```
G2A
└── Método: API pública de productos (documentada)
└── URL: https://api.g2a.com/v1/products?name={game}
└── Requiere API key (gratuita con límites)
└── Rate limit: 100 req/min
```

---

## 3. ARQUITECTURA DE SCRAPING EN PRODUCCIÓN

### 🏗️ Infraestructura propuesta

```
┌───────────────────────────────────────────────────┐
d┌────────────────────── GitHub Actions ───────────────────┐
d│  ├─── CheapShark Fetcher (diario 01:00)                          │
d│  │   └─── Genera data/cheapshark_deals.json                        │
d│  ├─── Eneba Scraper (diario 01:30)                              │
d│  │   └─── Genera data/eneba_prices.json                            │
d│  ├─── Grey Market Scraper (diario 02:00)                        │
d│  │   └─── Playwright + Chromium                                    │
d│  │   └─── Genera data/grey_market_prices.json                      │
d│  └─── Commit & Push                                             │
d│      └─── Trigger Dokploy deploy                                  │
d└───────────────────────────────────────────────────┘
d│                                                                   │
d└─────────────────────── Dokploy VPS ────────────────────────┘
d    ├─── Entrypoint (en cada deploy)                              │
d    │   ├─── Migrate + Seed stores                                  │
d    │   ├─── Import CheapShark JSON                                 │
d    │   ├─── Import Eneba JSON                                      │
d    │   └─── Import Grey Market JSON                                │
d    │                                                          │
d    ├─── Supervisor Schedule Runner (cada 60s)                    │
d    │   ├─── 03:00 - Import CheapShark                              │
d    │   ├─── 04:00 - Scrape Eneba                                   │
d    │   ├─── 05:00 - Import Grey Market                             │
d    │   ├─── 06:00 - Scrape All (fake solo tiendas sin real)       │
d    │   └─── Cada 6h - Update Steam metadata                        │
d    │                                                          │
d    └─── PostgreSQL + Redis                                       │
d        ├─── Games (1500+)                                         │
d        ├─── Products (2000+) con is_real_price                     │
d        └─── Cache de páginas (30min)                               │
d                                                           ┘
```

### 🛠️ Mejoras de la arquitectura

#### A. Sistema de "On-Demand Scraping"
Cuando un usuario busca un juego que NO está en la DB:

```php
// Nuevo: app/Services/OnDemandScraper.php
class OnDemandScraper {
    public function searchAndCreate(string $query): ?Game {
        // 1. Buscar en Steam API
        $steamApp = $this->searchSteam($query);
        
        // 2. Si existe, crear juego con metadatos
        if ($steamApp) {
            $game = Game::create([...$steamApp]);
            
            // 3. Scrapear precios en background (queue)
            ScrapeGamePrices::dispatch($game);
            
            return $game;
        }
        
        return null;
    }
}
```

**Flujo UX:**
1. Usuario busca "Star Wars Jedi Survivor"
2. Si no existe en DB → muestra "Buscando en tiendas..."
3. Background job busca en Steam + scrapea precios
4. En ~10-30s, recarga con resultados

#### B. Sistema de Precios en Tiempo Real (WebSockets)
Para juegos populares, precios que se actualizan sin recargar:

```javascript
// Vue componente
import Echo from 'laravel-echo';

// Escuchar actualizaciones de precios
Echo.channel(`game.${gameId}.prices`)
    .listen('PriceUpdated', (e) => {
        // Actualizar precio en la tabla
        updatePrice(e.storeId, e.newPrice);
    });
```

#### C. Colas Prioritarias
```php
// En routes/console.php
Schedule::command('prices:scrape-eneba --limit=50')
    ->dailyAt('04:00')
    ->onOneServer();

// Cola rápida para búsquedas de usuarios
Schedule::command('queue:work --queue=search,default')
    ->everyMinute();
```

---

## 4. FUNCIONALIDADES CORE (Benchmark vs AllKeyShop)

### ✅ YA IMPLEMENTADAS
| Funcionalidad | Estado | Notas |
|--------------|--------|-------|
| Comparación de precios | ✅ | Múltiples tiendas |
| Badge "Precio real" | ✅ | Distingue real vs estimado |
| Reviews de usuarios | ✅ | Sistema básico |
| SEO por juego | ✅ | Schema.org + meta tags |
| Búsqueda | ✅ | Por título |

### 🔧 PRIORIDAD ALTA (Próximo sprint)

#### 4.1 Price History / Evolución de Precios
```
AllKeyShop muestra gráficos de evolución de precios.

Implementación:
- Tabla: price_histories (game_id, store_id, price, recorded_at)
- Gráfico: Chart.js con línea de precios por tienda
- Trigger: Guardar snapshot cada vez que cambia un precio
```

#### 4.2 Alertas de Precio (Price Alerts)
```
AllKeyShop permite "Alertarme cuando baje de X €"

Implementación:
- Tabla: price_alerts (user_email, game_id, target_price)
- Comando: alerts:check cada hora
- Notificación: Email cuando precio <= target
- Futuro: Push notifications
```

#### 4.3 Juego No Encontrado → Búsqueda On-Demand
```
Cuando buscan un juego que no existe:

1. Buscar en Steam API
2. Si existe → Crear juego + Scrapear precios en background
3. Mostrar "Juego encontrado, cargando precios..."
4. Redirigir a página del juego cuando esté listo
```

#### 4.4 Páginas de Categoría / Género
```
AllKeyShop tiene: Strategy, RPG, Action, etc.

Implementación:
- Tags de Steam ya están en DB (genres)
- Route: /categoria/{genre}
- Cache por género
```

### 🔨 PRIORIDAD MEDIA

#### 4.5 Trending / Popular / Últimos Lanzamientos
```
AllKeyShop muestra:
- "Most Anticipated" (próximos lanzamientos)
- "Trending" (más visitados recientemente)
- "Free Games" (juegos gratis actuales)

Implementación:
- Trending: Contador de vistas en Redis (sorted set)
- Próximos lanzamientos: Games con release_date > now
- Free: Juegos con precio 0 en alguna tienda
```

#### 4.6 Cupones/Vouchers
```
AllKeyShop lista códigos de descuento por tienda.

Implementación:
- Tabla: vouchers (store_id, code, discount_percent, valid_until)
- Mostrar en página de tienda
- Aplicar automáticamente al precio
```

#### 4.7 Region Lock Info
```
Mostrar si una key es Global, EU, US, etc.

Implementación:
- Campo: products.region (ya existe)
- UI: Badge por región
- Filtro: "Solo keys globales"
```

#### 4.8 Historial de Reviews de Tiendas
```
AllKeyShop agrega reviews de Trustpilot, Reviews.io, etc.

Implementación:
- Tabla: store_reviews (store_id, source, rating, review_count)
- Scraper periódico de Trustpilot
- Mostrar estrellas en lista de tiendas
```

### 🔩 PRIORIDAD BAJA / FUTURO

#### 4.9 Comparador de Ediciones
```
AllKeyShop compara: Standard vs Deluxe vs Ultimate

Implementación:
- Campo: products.edition
- Agrupar por edición en página de juego
```

#### 4.10 DLCs
```
Listar DLCs disponibles para un juego

Implementación:
- Steam API tiene datos de DLC
- Relación: game_parent_id
```

#### 4.11 Multi-plataforma
```
AllKeyShop compara: PC, PS5, Xbox, Switch

Implementación:
- Campo: products.platform (ya existe)
- Filtro por plataforma
```

#### 4.12 News / Blog
```
AllKeyShop tiene noticias de gaming

Implementación:
- Integrar RSS de gaming news
- O usar AI para generar resúmenes
```

---

## 5. MEJORAS DE UX/UI (Inspiradas en AllKeyShop)

### 5.1 Home Page Redesign
```
Actual: Lista simple de juegos

Propuesta:
┌────────────────────────────────────────────┐
d┌────────────────────────────────────────────┐
d│  DESTACADOS (Carrusel)                              │
d│  ├── Forza Horizon 6 ─── GTA 6 ─── Subnautica 2       │
d└────────────────────────────────────────────┘
d│                                                    │
d├────────────────────────────────────────────┐
d│  🔥 TRENDING (Esta semana)                         │
d│  Grid de 8 juegos más visitados                     │
d└────────────────────────────────────────────┘
d│                                                    │
d├────────────────────────────────────────────┐
d│  🎯 MEJORES DESCUENTOS                             │
d│  Grid de juegos con >70% descuento                 │
d└────────────────────────────────────────────┘
d│                                                    │
d├────────────────────────────────────────────┐
d│  🆕 ÚLTIMOS LANZAMIENTOS                          │
d│  Juegos con release_date ≤ 30 días                │
d└────────────────────────────────────────────┘
d│                                                    │
d├────────────────────────────────────────────┐
d│  🎁 JUEGOS GRATIS                                 │
d│  Juegos con precio 0 en alguna tienda             │
d└────────────────────────────────────────────┘
d│                                                    │
d├────────────────────────────────────────────┐
d│  📈 TODOS LOS JUEGOS (Paginación)                │
d│  Filtros: Género, Precio, Año, Rating             │
d└────────────────────────────────────────────┘
d└───────────────────────────────────────────────────┘
```

### 5.2 Página de Juego Mejorada
```
Actual: Tabla simple de precios

Propuesta:
├─── Header con cover + info + Metacritic
├─── Tabs: Precios | Historial | DLCs | Noticias
├─── Tabla de precios con:
│   ├── Filtro por región (Global, EU, US)
│   ├── Filtro por plataforma (Steam, GOG, Xbox)
│   ├── Ordenación por precio/descuento/confianza
│   └── Badge de "Oficial" vs "Key Reseller"
├─── Gráfico de evolución de precios (6 meses)
├─── Botón "Alertarme cuando baje"
├─── Reviews de usuarios
└─── Juegos similares
```

### 5.3 Filtros Avanzados
```
AllKeyShop permite filtrar por:
- Tienda específica
- Región (Global, EU, US...)
- Plataforma (Steam, GOG, Xbox...)
- Tipo (Key, Gift, Account)
- Sólo oficiales / Sólo grey market
- Rango de precio
- % de descuento
```

---

## 6. RENDIMIENTO Y ESCALABILIDAD

### 6.1 Caché Estrategia
```
Actual: Cache por página (30 min)

Mejoras:
- Redis para trending (sorted sets con TTL)
- Cache de precios por juego (5 min para juegos populares)
- Cache de búsquedas (1 hora)
- CDN para imágenes (CloudFlare / CloudFront)
```

### 6.2 Database Optimizations
```
- Índices: (game_id, store_id), (is_real_price, current_price)
- Materialized view: lowest_price_per_game
- Partitioning: price_histories por mes
```

### 6.3 Rate Limiting para Scrapers
```
Actual: Sin control

Propuesta:
- Eneba: 1 req/s (ya implementado)
- Otros: según robots.txt
- Proxy rotation para grey market
- User-Agent rotation
```

---

## 7. ROADMAP

### 📅 Q2 2026 (Mayo-Junio)
- [ ] Price History con gráficos
- [ ] Price Alerts por email
- [ ] On-Demand scraping (búsqueda de juegos nuevos)
- [ ] Integrar Gamesplanet API
- [ ] Home page redesign con secciones
- [ ] Filtros por género/categoría

### 📅 Q3 2026 (Julio-Septiembre)
- [ ] Gamivo scraper
- [ ] G2A API
- [ ] Region lock badges
- [ ] Cupones/vouchers
- [ ] Multi-plataforma (PS5, Xbox)
- [ ] Reviews de tiendas (Trustpilot)

### 📅 Q4 2026 (Octubre-Diciembre)
- [ ] Mobile app (PWA)
- [ ] Push notifications para alerts
- [ ] API pública
- [ ] Affiliate dashboard
- [ ] Auto-optimización de precios con ML

---

## 8. TABLA RESUMEN DE TIENDAS RECOMENDADAS

| Prioridad | Tienda | Tipo | Método | Complejidad | Impacto |
|-----------|--------|------|--------|-------------|---------|
| P0 | Gamesplanet | Oficial | API | ⭐⭐ | ⭐⭐⭐⭐ |
| P0 | Gamivo | Grey | Apollo GraphQL | ⭐⭐⭐ | ⭐⭐⭐⭐⭐ |
| P1 | GamersGate | Oficial | HTML Scrape | ⭐⭐ | ⭐⭐⭐ |
| P1 | G2A | Grey | API | ⭐⭐⭐ | ⭐⭐⭐⭐⭐ |
| P1 | 2Game | Oficial | HTML Scrape | ⭐⭐ | ⭐⭐⭐ |
| P2 | IndieGala | Oficial | AJAX API | ⭐⭐ | ⭐⭐⭐ |
| P2 | K4G | Grey | HTML Scrape | ⭐⭐⭐ | ⭐⭐⭐ |
| P2 | HRK | Grey | HTML Scrape | ⭐⭐⭐ | ⭐⭐⭐ |
| P3 | Driffle | Grey | HTML Scrape | ⭐⭐⭐ | ⭐⭐ |
| P3 | Bcdkey | Grey | HTML Scrape | ⭐⭐ | ⭐⭐ |
