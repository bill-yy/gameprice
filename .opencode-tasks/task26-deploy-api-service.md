# Task 26: Prepare API Service for Dokploy deployment

## Context
GamePrice has a standalone API service in `/api-service/` directory (created in Task 20). Need to prepare it for deployment on Dokploy.

## What exists
- `/api-service/` — Laravel 12 API project with:
  - `app/Http/Controllers/Api/V1/SearchController.php`
  - `app/Http/Controllers/Api/V1/StoreController.php`
  - `routes/api.php` with endpoints: `/api/v1/search`, `/prices/{store}`, `/stores`, `/deals`
  - Middleware: ApiKeyMiddleware, RateLimitMiddleware
  - All 5 scraper services copied
  - `Dockerfile`, `docker-compose.yml`, `docker/nginx.conf`

## What to build/fix
1. Verify and fix the `/api-service/Dockerfile`:
   - Should use PHP 8.4-FPM + nginx (similar to main app)
   - Must build assets (though API has no frontend)
   - Must run migrations on startup
   - Expose port 80

2. Create `/api-service/docker/entrypoint.sh`:
   - Run `php artisan migrate --force`
   - Start supervisor (nginx + php-fpm)

3. Ensure `/api-service/.env.example` has all required vars:
   - APP_KEY, DB_CONNECTION, DB_HOST, etc.
   - API_KEY for auth middleware

4. Add health check endpoint `GET /api/health` that returns `{"status": "ok"}`

5. Update README with Dokploy deployment instructions

## Important constraints
- This is a SEPARATE app from the main GamePrice
- It will be deployed as a separate Dokploy application
- Keep it lightweight — no queue workers needed, no scheduler
