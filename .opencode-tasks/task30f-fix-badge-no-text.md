# Task 30F: Fix freshness badge - hide when no prices

## Context
GamePrice Vue 3. In `resources/js/Pages/GameShow.vue`, the `freshnessBadge` computed property returns `{ text: 'Sin precios reales', color: 'gray' }` when `hoursSinceUpdate.value === null`. The user wants NOTHING to show in this case.

## What exists
Line 133-138 of GameShow.vue:
```js
const freshnessBadge = computed(() => {
    if (hoursSinceUpdate.value === null) return { text: 'Sin precios reales', color: 'gray' };
    if (hoursSinceUpdate.value < 1) return { text: '✅ Precios actualizados hace poco', color: 'green' };
    if (hoursSinceUpdate.value <= 24) return { text: `✅ Precios actualizados hace ${hoursSinceUpdate.value}h`, color: 'green' };
    return { text: `⚠ Precios de hace ${hoursSinceUpdate.value}h`, color: 'yellow' };
});
```

The template already has `v-if="freshnessBadge"` on line 344.

## What to build
Change line 134 from:
```js
if (hoursSinceUpdate.value === null) return { text: 'Sin precios reales', color: 'gray' };
```
to:
```js
if (hoursSinceUpdate.value === null) return null;
```

This will hide the badge entirely when no `price_fetched_at` data exists.

## Important constraints
- Only change this one line
- Commit: "Task 30F: Hide freshness badge when no price data"
