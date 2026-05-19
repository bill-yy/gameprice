# Task 30H: Fix route cache not clearing on deploy

## Context
GamePrice deployed on Dokploy. New routes added to `routes/web.php` are returning 404 in production, but old routes work fine. The issue is that `docker/entrypoint.sh` clears config cache and rebuilds it, but never clears Laravel's route cache. When `bootstrap/cache/routes.php` exists from a previous deploy, Laravel uses the cached routes and ignores new ones in `routes/web.php`.

## What exists
`docker/entrypoint.sh` lines 5-11:
```sh
php artisan config:clear 2>/dev/null || true
php artisan cache:clear 2>/dev/null || true

# Cache configs with the actual runtime environment variables
php artisan config:cache || true
php artisan view:cache || true
php artisan event:cache || true
```

## What to build
Add `php artisan route:clear 2>/dev/null || true` immediately after `php artisan cache:clear` (before the comment `# Cache configs...`).

Also add `php artisan route:cache || true` after `php artisan event:cache || true` (so routes are cached for performance after being cleared).

The final block should be:
```sh
php artisan config:clear 2>/dev/null || true
php artisan cache:clear 2>/dev/null || true
php artisan route:clear 2>/dev/null || true

# Cache configs with the actual runtime environment variables
php artisan config:cache || true
php artisan view:cache || true
php artisan event:cache || true
php artisan route:cache || true
```

## Important constraints
- Only modify `docker/entrypoint.sh`
- Commit: "Task 30H: Clear and rebuild route cache on deploy"
