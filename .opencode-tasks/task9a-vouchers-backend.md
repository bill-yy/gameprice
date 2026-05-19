# Task: Vouchers Backend

## Context
This is a Laravel 12 project at /tmp/gameprice-repo.

## What to build (backend only)

### 1. Create migration
Create database/migrations/2026_05_12_000001_create_vouchers_table.php:
```php
Schema::create('vouchers', function (Blueprint $table) {
    $table->id();
    $table->foreignId('store_id')->constrained()->onDelete('cascade');
    $table->string('code');
    $table->decimal('discount_value', 8, 2);
    $table->enum('discount_type', ['fixed', 'percentage'])->default('fixed');
    $table->date('valid_from');
    $table->date('valid_until');
    $table->boolean('is_active')->default(true);
    $table->timestamps();
});
```

### 2. Create app/Models/Voucher.php
Model with belongsTo Store relation.

### 3. Create app/Http/Controllers/VoucherController.php
- show($storeId) — return active vouchers for store as JSON

### 4. Update routes/web.php
Add: Route::get('/api/vouchers/{store}', [VoucherController::class, 'show']);

### 5. Update app/Models/Store.php
Add hasMany Vouchers relation.

## Important
- Only backend code, NO frontend changes
- Minimal changes to existing files
