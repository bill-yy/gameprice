# Task: Store Reviews & Ratings

## Context
This is a Laravel 12 + Vue 3 + Inertia project at /tmp/gameprice-repo.

## What exists
- Store model has: name, slug, logo_url, website_url, is_active, is_official, rating, review_count
- GameShow.vue shows store name with badge (Oficial/Key Reseller)
- StoreSeeder has stores with default rating=null

## What to build

### 1. Update StoreSeeder.php
Add real ratings for known stores. Use updateOrCreate to update existing stores:
- Steam: 4.5/5 (review_count: 1000000)
- GOG: 4.7/5 (review_count: 500000)
- Epic Games Store: 3.8/5 (review_count: 800000)
- Humble Bundle: 4.6/5 (review_count: 400000)
- Fanatical: 4.4/5 (review_count: 300000)
- Gamesplanet: 4.5/5 (review_count: 200000)
- GamersGate: 4.2/5 (review_count: 150000)
- GreenManGaming: 4.3/5 (review_count: 250000)
- Eneba: 4.0/5 (review_count: 100000)
- G2A: 3.5/5 (review_count: 200000)
- Gamivo: 3.8/5 (review_count: 50000)
- Kinguin: 3.6/5 (review_count: 80000)
- Instant Gaming: 4.2/5 (review_count: 120000)
- Others: 4.0/5 (review_count: 10000)

### 2. Update GameShow.vue store display
In the price table, show store rating as stars next to the store name:
- Display 5 stars, filled based on rating
- Show review count in parentheses
- Example: "Fanatical ⭐⭐⭐⭐⭐ (4.4 · 300K reseñas)"
- Use simple unicode stars: ★ (filled) and ☆ (empty)
- Keep it small (text-xs)

### 3. Update Home.vue (optional)
If Home.vue shows store info anywhere, add ratings.

## Important
- Minimal changes to GameShow.vue
- Only update the store name cell in the price table
- Don't break existing badges (Oficial/Key Reseller)
