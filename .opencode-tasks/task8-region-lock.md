# Task: Region Lock Badges

## Context
This is a Laravel 12 + Vue 3 + Inertia project at /tmp/gameprice-repo.

## What exists
- GameShow.vue has a price table with: Tienda, Plataforma, Región, Precio, Descuento, Ahorro, Acción
- The "Región" column shows product.region (e.g., "global", "EU", "US")
- Products table has: region field (string, nullable)

## What to build

### 1. Update GameShow.vue region column
In the price table, enhance the region display:
- If product.region is null, empty, or 'global': show 🌍 "Global" with green badge
- If product.region is 'EU', 'Europe', 'europe': show 🇪🇺 "Europa" with blue badge
- If product.region is 'US', 'USA', 'NA', 'North America': show 🇺🇸 "Norteamérica" with blue badge
- If product.region is 'LATAM', 'Latin America', 'latam': show 🌎 "LATAM" with yellow badge
- If product.region is 'RU', 'Russia', 'CIS': show 🇷🇺 "Rusia/CIS" with red badge
- If product.region is 'ASIA', 'Asia', 'APAC': show 🌏 "Asia" with purple badge
- Any other value: show the raw value with grey badge

Use inline-flex with Tailwind classes. Keep it small and readable.

### 2. Update migration (if needed)
If there's no region column on products, add it. But likely it already exists.

### 3. Update scrapers
When saving products from scrapers (Eneba, Gamivo, G2A, CheapShark), try to extract region info:
- If the product title contains "(Global)", "(EU)", "(US)", "(LATAM)", etc., set region accordingly
- Default to 'global' if not specified

This is optional for this task - focus on the UI first.

## Important
- Minimal changes to GameShow.vue
- Keep the table clean and readable
- Badges should be small (text-xs, px-2, py-0.5)
