<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\PriceAlert;
use Illuminate\Http\Request;

class PriceAlertController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'game_id' => ['required', 'exists:games,id'],
            'email' => ['required', 'email', 'max:255'],
            'target_price' => ['required', 'numeric', 'min:0'],
        ]);

        $lowestPrice = Game::findOrFail($validated['game_id'])
            ->products()
            ->where('is_real_price', true)
            ->orderBy('current_price')
            ->value('current_price');

        if ($lowestPrice === null) {
            return back()->with('error', 'No hay precios disponibles para este juego.');
        }

        if ($validated['target_price'] >= $lowestPrice) {
            return back()->with('error', 'El precio objetivo debe ser menor que el precio actual más bajo (' . number_format($lowestPrice, 2) . '€).');
        }

        PriceAlert::create([
            'game_id' => $validated['game_id'],
            'email' => $validated['email'],
            'target_price' => $validated['target_price'],
            'is_active' => true,
        ]);

        return back()->with('success', 'Alerta de precio creada correctamente.');
    }
}
