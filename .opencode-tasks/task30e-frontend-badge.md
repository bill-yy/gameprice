# Task 30E: Frontend freshness badge and remove manual refresh button

## Context
GamePrice Vue 3 + Inertia. GameShow page at `resources/js/Pages/GameShow.vue`.

## What exists
- `resources/js/Pages/GameShow.vue` displays game details and products list
- `products` array comes from controller with `current_price`, `store`, `price_fetched_at` (new field)
- Manual refresh button exists in the page

## What to build
1. In `resources/js/Pages/GameShow.vue`:
   - Remove or hide the manual "Actualizar precios" button
   - Add a computed property `lastPriceUpdate` that finds the newest `price_fetched_at` across all products
   - Display a small badge/text: "✅ Precios actualizados hace Xh" or "⚠ Precios de hace Xh" if >24h
   - Add `price_fetched_at` to the Inertia props mapping (it's already in the product object from controller)
2. Commit: "Task 30E: Add price freshness badge and remove manual refresh button"

## Exact changes needed
- Remove `<button>` that triggers manual refresh (or comment it out)
- Add computed:
```js
const lastPriceUpdate = computed(() => {
    if (!props.products?.length) return null;
    const dates = props.products
        .map(p => p.price_fetched_at ? new Date(p.price_fetched_at) : null)
        .filter(Boolean);
    if (!dates.length) return null;
    return new Date(Math.max(...dates));
});

const hoursSinceUpdate = computed(() => {
    if (!lastPriceUpdate.value) return null;
    return Math.floor((Date.now() - lastPriceUpdate.value.getTime()) / (1000 * 60 * 60));
});

const freshnessBadge = computed(() => {
    if (!hoursSinceUpdate.value) return { text: 'Sin precios reales', color: 'gray' };
    if (hoursSinceUpdate.value < 1) return { text: '✅ Precios actualizados hace poco', color: 'green' };
    if (hoursSinceUpdate.value <= 24) return { text: `✅ Precios actualizados hace ${hoursSinceUpdate.value}h`, color: 'green' };
    return { text: `⚠ Precios de hace ${hoursSinceUpdate.value}h`, color: 'yellow' };
});
```
- Render badge somewhere near the prices section

## Important constraints
- Keep all existing layout and styling
- Badge must be unobtrusive (small text)
- Use Tailwind classes for colors (text-green-600, text-yellow-600, text-gray-500)
- Do NOT break existing price display
- Commit separately
