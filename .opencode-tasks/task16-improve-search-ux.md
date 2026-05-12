# Task: Improve Search UX - On-Demand Search UI

## Context
This is a Laravel 12 + Vue 3 + Inertia project at /tmp/gameprice-repo.

## Problem
When a user searches for a game that doesn't exist in our DB:
1. The backend tries on-demand search via Steam API
2. If found, it redirects to the game page (good!)
3. If NOT found, it shows "No se encontraron juegos" with no options (bad!)

## Current Flow
- User types "zelda tears of the kingdom" and submits
- GameController::index() runs OnDemandSearchService
- If not found: returns Home with 0 results, no feedback

## What to Build

### 1. Add "Search on Steam" button to Home.vue
In the "No results" section (line 319-321), add:
- A message: "No encontramos 'zelda tears of the kingdom' en nuestra base de datos"
- A button: "🔍 Buscar en Steam"
- When clicked, show a loading spinner
- Use Inertia to make a POST request to a new endpoint
- After search completes, either redirect to the new game or show "No encontrado en Steam"

### 2. Create POST endpoint for on-demand search
In GameController or a new SearchController:
```php
public function searchOnDemand(Request $request)
{
    $query = $request->input('query');
    $onDemand = app(OnDemandSearchService::class);
    $found = $onDemand->search($query);
    
    if ($found) {
        return redirect()->route('game.show', $found->slug);
    }
    
    return back()->with('error', 'No se encontró el juego en Steam');
}
```

### 3. Update Home.vue
- Add a prop for onDemandSearchUrl
- Add state: `searchingOnDemand` (boolean)
- When "Buscar en Steam" clicked:
  - Set `searchingOnDemand = true`
  - POST to the endpoint
  - On success: Inertia handles redirect automatically
  - On error: show message, set `searchingOnDemand = false`

### 4. Update GameController::index()
- Pass `onDemandSearchUrl` to Home.vue
- The backend on-demand search should be REMOVED or made optional
- Let the user trigger it explicitly via the button

## Important
- Use Inertia's router.post() for the AJAX request
- Show loading state with a spinner
- Keep the dark theme
- Minimal changes to existing code
