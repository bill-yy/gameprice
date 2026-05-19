# Task: Price Alert Email Notifications

## Context
This is a Laravel 12 project at /tmp/gameprice-repo.

## What exists
- PriceAlert model: game_id, email, target_price, is_active, notified_at
- PriceAlertController: stores alerts
- GameShow.vue: alert form with email/target_price
- Laravel Queue configured with database driver
- PriceHistory model records price changes

## What to build

### 1. Create app/Console/Commands/CheckPriceAlerts.php
Command that runs every hour:
- For each active alert where notified_at is null:
  - Get the game's lowest real price
  - If lowest price <= target_price:
    - Send email notification
    - Update notified_at to now
    - Set is_active to false
- Signature: alerts:check

### 2. Create app/Mail/PriceAlertNotification.php
Mailable with:
- Subject: "🚨 ¡Bajó de precio! {{ game.title }} ahora desde {{ price }}€"
- Markdown view with game info, price, store, link to game page
- Use Laravel's Markdown mailable

### 3. Create resources/views/emails/price-alert.blade.php
Simple dark-themed email:
- Game title and cover image
- Old price vs new price
- Store name with link
- CTA button "Ver oferta"
- Unsubscribe link

### 4. Update routes/console.php
Add schedule: alerts:check every hour

### 5. Update docker/entrypoint.sh
No changes needed (queue worker handles jobs)

### 6. Update PriceAlert model
Add method: shouldNotify() — checks if price is below target and not yet notified

## Important
- Use Laravel Queue for sending emails (dispatch job)
- Handle errors gracefully (log failed sends)
- Don't spam: only send once per alert
- Minimal changes to existing files
