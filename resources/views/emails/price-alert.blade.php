@component('mail::message')
# 🎮 ¡Tu juego ha bajado de precio!

**{{ $alert->game->title }}** ha alcanzado tu precio objetivo.

---

@if($alert->game->cover_image)
![{{ $alert->game->title }}]({{ $alert->game->cover_image }})
@endif

| | Precio |
|---|---|
| Precio original | ~~{{ number_format($product->original_price, 2) }}€~~ |
| **Tu precio objetivo** | {{ number_format($alert->target_price, 2) }}€ |
| **Precio actual** | **{{ number_format($product->current_price, 2) }}€** |

🏪 **Tienda:** [{{ $product->store->name }}]({{ $product->affiliate_url ?? $product->url }})

@component('mail::button', ['url' => route('game.show', $alert->game)])
Ver oferta
@endcomponent

---

<sub>Estás recibiendo este email porque creaste una alerta de precio en **{{ config('app.name') }}**.</sub>

<sub>Esta alerta ha sido desactivada. No recibirás más notificaciones para este juego.</sub>
@endcomponent
