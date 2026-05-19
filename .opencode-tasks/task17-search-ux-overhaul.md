# Task 17: Search UX Overhaul — Smart Autocomplete + Auto On-Demand

## Context
GamePrice is a Laravel 12 + Vue 3 + Inertia.js price comparison site. The search experience has critical UX problems:
- Two duplicate search inputs (header + hero section)
- When a game is not in DB, user must scroll down and click "Buscar en Steam" manually
- No autocomplete/suggestions while typing
- Games with future release dates should show "Próximamente" badge

## What exists
- `resources/js/Components/SearchBar.vue` — basic input with enter key search
- `resources/js/Pages/Home.vue` — has hero search + header search (DUPLICATE)
- `app/Services/OnDemandSearchService.php` — searches Steam Store API, creates game if found
- `app/Http/Controllers/GameController.php` — `searchOnDemand()` endpoint POST `/search/steam`
- Game model has `release_date`, `steam_app_id`, `slug`, `title`, `cover_image`

## What to build (numbered)

1. **Smart Autocomplete Component** (`resources/js/Components/SearchAutocomplete.vue`)
   - Replace both SearchBar instances with this new component
   - While typing (≥3 chars), debounce 300ms, fetch suggestions from backend
   - Create new endpoint: `GET /api/search/suggestions?query={term}` in `GameController`
   - Endpoint searches DB first: `Game::where('title', 'ilike', "%{$query}%")->limit(5)->get(['slug','title','cover_image','release_date'])`
   - If < 5 DB results, fill remaining slots with Steam Store API search results
   - Steam results should show "🌐 Steam" label to distinguish from DB results
   - Clicking a DB result → navigates to `/juego/{slug}`
   - Clicking a Steam result → triggers on-demand import + redirect
   - Show "No se encontraron resultados" if nothing found
   - Keyboard navigation: ↑↓ arrows + Enter to select
   - Close on Escape or click outside

2. **Auto On-Demand Search**
   - In the autocomplete, if the user presses Enter and NO suggestion is selected:
     - Automatically trigger the on-demand Steam search (POST `/search/steam`)
     - Show loading state "Buscando en Steam..."
     - If found → redirect to new game page
     - If not found → show inline message "No encontramos '{query}' ni en nuestra base de datos ni en Steam"
   - Remove the manual "🔍 Buscar en Steam" button from Home.vue empty state

3. **Remove Duplicate Search**
   - Remove the search input from the Hero section in `Home.vue`
   - Keep only the header search bar (make it the canonical search)
   - The header search should be present on ALL pages (move to `AuthenticatedLayout` or a shared layout component if needed — or ensure it stays in the header across pages)
   - On Home.vue, the hero section should just have the heading + subtitle, no search input

4. **"Próximamente" Badge**
   - In `GameCard.vue`, if `game.release_date` is in the future (> now()), show a small badge:
     - Text: "Próximamente"
     - Color: amber/yellow (bg-amber-600 or similar)
     - Position: top-right corner of the card image, absolute positioned
   - Also show in `GameShow.vue` near the title if release_date is future
   - Format date: "Lanzamiento: 15 mar 2026" in Spanish

5. **Header Search Sticky & Scroll Behavior**
   - The header search bar should be sticky (`sticky top-0 z-50`)
   - When searching from any page, after submitting, scroll smoothly to results section

## Important constraints
- Use Tailwind CSS for all styling
- Follow existing Vue 3 Composition API patterns (`<script setup>`)
- Use Inertia.js `router.get()` / `router.post()` for navigation
- The suggestions endpoint must be fast (DB query + optional Steam API call)
- Steam API calls must have timeout and error handling
- Do NOT implement the "fallback with external links" feature (user explicitly rejected this)
- All text in Spanish
- Maintain dark theme (gray-900 backgrounds, gray-300 text)

## Files to modify/create
- CREATE: `resources/js/Components/SearchAutocomplete.vue`
- MODIFY: `resources/js/Pages/Home.vue` — remove hero search, remove manual Steam button
- MODIFY: `resources/js/Components/GameCard.vue` — add Próximamente badge
- MODIFY: `resources/js/Pages/GameShow.vue` — add Próximamente badge near title
- MODIFY: `app/Http/Controllers/GameController.php` — add `searchSuggestions()` endpoint
- MODIFY: `routes/web.php` — add GET `/api/search/suggestions` route
- MODIFY: `resources/js/Components/SearchBar.vue` — replace with SearchAutocomplete or update to use it
