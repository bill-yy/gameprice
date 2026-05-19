# Task: Discount Vouchers

## Context
This is a Laravel 12 + Vue 3 + Inertia project at /tmp/gameprice-repo.

## What exists
- GameShow.vue shows a price comparison table
- Products have: current_price, original_price, discount_percentage
- Stores have: name, website_url, is_official

## What to build

### 1. Create database schema
Create a migration for vouchers table:
```php
Schema::create('vouchers', function (Blueprint $table) {
    $table->id();
    $table->foreignId('store_id')->constrained()->onDelete('cascade');
    $table->string('code');
    $table->decimal('discount_value', 8, 2); // absolute discount amount
    $table->enum('discount_type', ['fixed', 'percentage'])->default('fixed');
    $table->date('valid_from');
    $table->date('valid_until');
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```

### 2. Create app/Models/Voucher.php
Model with relations to Store.

### 3. Create app/Http/Controllers/VoucherController.php
- index() — list all active vouchers
- store() — create new voucher (admin only, no auth needed for now)
- show($storeId) — show vouchers for a specific store

### 4. Update GameShow.vue
In the price table, if a store has an active voucher:
- Show the voucher code next to the price
- Show the final price after applying the voucher
- Add a "copy code" button

Example: 
```
Fanatical Oficial
3.19€ → 2.69€ con código SUMMER10
[Copiar código]
```

### 5. Create database/seeders/VoucherSeeder.php
Add sample vouchers for testing:
- Fanatical: SAVE5 (-5%)
- GamersGate: GG10 (-10%)
- Gamesplanet: PLANET15 (-15%)

### 6. Update routes/web.php
Add: Route::get('/api/vouchers/{store}', [VoucherController::class, 'show']);

## Important
- Minimal changes to existing files
- Show voucher only if it's valid (current date between valid_from and valid_until)
- Don't break existing price display
