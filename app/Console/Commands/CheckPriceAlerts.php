<?php

namespace App\Console\Commands;

use App\Models\PriceAlert;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckPriceAlerts extends Command
{
    protected $signature = 'alerts:check';

    protected $description = 'Check price alerts and notify users when target price is reached';

    public function handle(): int
    {
        $alerts = PriceAlert::query()
            ->where('is_active', true)
            ->whereNull('notified_at')
            ->with('game.products')
            ->get();

        foreach ($alerts as $alert) {
            $lowestPrice = $alert->game->products->min('current_price');

            if ($lowestPrice !== null && $lowestPrice <= $alert->target_price) {
                Log::info("[PriceAlert] Notificación enviada a {$alert->email} para {$alert->game->title}. Precio actual: {$lowestPrice}€, objetivo: {$alert->target_price}€");

                $alert->update([
                    'notified_at' => now(),
                    'is_active' => false,
                ]);

                $this->info("Notificada alerta #{$alert->id} -> {$alert->email}");
            }
        }

        $this->info('Verificación de alertas completada.');

        return self::SUCCESS;
    }
}
