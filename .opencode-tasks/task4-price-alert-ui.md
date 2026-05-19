# Task: Price Alert UI

## Context
This is a Laravel 12 + Vue 3 + Inertia project at /tmp/gameprice-repo.

## What exists
- PriceAlert model with: game_id, email, target_price, is_active, notified_at
- PriceAlertController::store() handles creating alerts
- CheckPriceAlerts command runs hourly via scheduler
- GameShow.vue shows game details and price table

## What to build

### 1. Update resources/js/Pages/GameShow.vue
Add a "🔔 Alertas de precio" section below the price history chart.

The section should contain:
- A simple form with email input and target price input
- A submit button "Crear alerta"
- Show current lowest real price as hint: "Precio actual: X€"
- Use Inertia form submission to POST /alerts (route already exists)
- Show success message when alert is created
- Dark theme, Spanish text

The form should:
- Pre-fill target price with current lowest real price - 10%
- Validate email format
- Validate target_price is positive number

### 2. Update app/Http/Controllers/PriceAlertController.php
Modify store() to:
- Accept Inertia requests (return redirect back with flash message)
- Add validation: target_price must be less than current lowest price
- Flash 'success' or 'error' message

### 3. Add route verification
Ensure routes/web.php has:
```php
Route::post('/alerts', [\App\Http\Controllers\PriceAlertController::class, 'store'])->name('alerts.store');
```
If not, add it.

## Important
- Do NOT break existing functionality
- Keep dark theme
- All text in Spanish
- Minimal changes
