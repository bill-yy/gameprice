# Task: Advanced Filters on Home Page

## Context
This is a Laravel 12 + Vue 3 + Inertia project at /tmp/gameprice-repo.

## What exists
- Home.vue shows: hero, trending, best deals, new releases, all games
- GameController::index() returns games with search filter
- Games have: title, release_date, genres, products (with store, price, discount, region)

## What to build

### 1. Update GameController::index()
Add filters:
- `price_min` and `price_max` — filter games by lowest real price
- `discount_min` — filter by minimum discount percentage
- `region` — filter by product region (global, EU, US, etc.)
- `store` — filter by store slug (comma-separated for multiple)
- `sort` — sort by: price_asc, price_desc, discount_desc, release_desc, name_asc

Example query:
```
/?price_max=10&discount_min=50&region=global&store=fanatical,gamesplanet&sort=discount_desc
```

### 2. Update Home.vue
Add a filter panel above "Todos los juegos":
- Price range: min/max inputs
- Min discount: slider or input
- Region: dropdown (Global, EU, US, LATAM, etc.)
- Store: multi-select checkboxes
- Sort: dropdown
- Apply/Reset buttons

Use Tailwind for styling. Keep it compact and dark-themed.

### 3. Preserve filters in URL
Use Inertia's query string preservation so filters stay in URL when navigating.

### 4. Update the "Todos los juegos" section
Show filtered results with a count: "Mostrando X juegos"

## Important
- Minimal changes to existing Home.vue
- Add the filter panel, don't remove existing sections
- Keep the dark theme
- Responsive design
