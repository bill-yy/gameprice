<?php

namespace App\Http\Controllers;

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

        PriceAlert::create([
            'game_id' => $validated['game_id'],
            'email' => $validated['email'],
            'target_price' => $validated['target_price'],
            'is_active' => true,
        ]);

        return back()->with('success', 'Alerta de precio creada correctamente.');
    }
}
