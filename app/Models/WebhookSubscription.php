<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookSubscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'api_key_id',
        'url',
        'event_type',
        'game_name',
        'threshold_price',
        'is_active',
        'last_triggered_at',
        'trigger_count',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'threshold_price' => 'decimal:2',
        'last_triggered_at' => 'datetime',
    ];

    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(ApiKey::class);
    }

    /**
     * Trigger the webhook with price drop data.
     */
    public function trigger(array $data): bool
    {
        try {
            $response = \Illuminate\Support\Facades\Http::timeout(10)
                ->post($this->url, [
                    'event' => $this->event_type,
                    'game' => $data['game_name'] ?? $this->game_name,
                    'store' => $data['store'] ?? null,
                    'price' => $data['price'] ?? null,
                    'previous_price' => $data['previous_price'] ?? null,
                    'discount_percent' => $data['discount_percent'] ?? null,
                    'url' => $data['url'] ?? null,
                    'timestamp' => now()->toIso8601String(),
                ]);

            $this->update([
                'last_triggered_at' => now(),
                'trigger_count' => $this->trigger_count + 1,
            ]);

            return $response->successful();
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Webhook trigger failed', [
                'webhook_id' => $this->id,
                'url' => $this->url,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
