# Task: Store Trust Badges

## Context
This is a Laravel 12 + Vue 3 + Inertia project at /tmp/gameprice-repo.

## What exists
- database/seeders/StoreSeeder.php has stores with is_official boolean
- GameShow.vue shows a price comparison table with store name, platform, region, price, discount
- Store model has: name, slug, logo_url, website_url, is_active, is_official, rating, review_count

## What to build

### 1. Update StoreSeeder.php
For each existing store, set is_official based on this mapping:
- Official (true): Steam, GOG, Epic Games Store, Humble Bundle, Fanatical, Gamesplanet, GamersGate, GreenManGaming, WinGameStore, GameBillet, 2Game
- Key Reseller (false): Eneba, Gamivo, G2A, Kinguin, Instant Gaming, HRK, K4G, IndieGala

Use updateOrCreate to update existing stores.

### 2. Update GameShow.vue price table
In the price comparison table, add a small badge next to the store name:
- If store.is_official: show green badge "Oficial"
- If !store.is_official: show orange badge "Key Reseller"
- Use inline style or Tailwind classes

### 3. Update GameCard.vue (if needed)
If GameCard shows store info, add same badge logic.

### 4. Add store logos to price table
Show store.logo_url as a small 20x20px image next to the store name in the price table.

## Important
- Minimal changes to existing files
- Keep the existing table structure
- Badges should be small and unobtrusive
