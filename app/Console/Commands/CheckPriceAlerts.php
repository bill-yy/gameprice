<?php

namespace App\Console\Commands;

use App\Mail\PriceAlertNotification;
use App\Models\PriceAlert;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class CheckPriceAlerts extends Command
{
    protected $signature = 'alerts:check';

    protected $description = 'Check price alerts and notify users when target price is reached';

    public function handle(): int
    {
        $alerts = PriceAlert::query()
            ->where('is_active', true)
            ->whereNull('notified_at')
            ->with('game')
            ->get();

        $notified = 0;

        foreach ($alerts as $alert) {
            try {
                $product = $alert->game->products()
                    ->where('is_real_price', true)
                    ->orderBy('current_price')
                    ->with('store')
                    ->first();

                if (! $product || ! $alert->shouldNotify($product->current_price)) {
                    continue;
                }

                Mail::to($alert->email)->queue(new PriceAlertNotification($alert, $product));

                $alert->update([
                    'notified_at' => now(),
                    'is_active' => false,
                ]);

                $notified++;
                $this->info("Notificada alerta #{$alert->id} -> {$alert->email}");
            } catch (\Throwable $e) {
                Log::error('[PriceAlert] Failed to send notification', [
                    'alert_id' => $alert->id,
                    'error' => $e->getMessage(),
                ]);
                $this->error("Error processing alert #{$alert->id}: {$e->getMessage()}");
            }
        }

        $this->info("Verificación completada. {$notified} notificaciones enviadas.");

        return self::SUCCESS;
    }
}
