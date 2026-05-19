# Task 30A: Add price_fetched_at to products table

## Context
GamePrice Laravel 12 + Vue 3 + Inertia + Docker + Dokploy.
Production: https://baratoya.billytech.es

## What exists
- Table `products` exists with fields: game_id, store_id, current_price, original_price, discount_percent, url, affiliate_url, is_real_price, currency, platform, region, type, in_stock, timestamps
- Model `Product` at `app/Models/Product.php`

## What to build
1. Create migration to add `price_fetched_at` (nullable timestamp) to `products` table
2. Update `Product` model: add `price_fetched_at` to `$fillable` array
3. Commit with message: "Task 30A: Add price_fetched_at column to products"

## Exact code needed

Migration:
```php
Schema::table('products', function (Blueprint $table) {
    $table->timestamp('price_fetched_at')->nullable()->after('in_stock');
});
```

Model fillable array should include `'price_fetched_at'`.

## Important constraints
- Migration must be safe (nullable, no default, no existing data breakage)
- Use `after('in_stock')` for clean column ordering
- No other changes
