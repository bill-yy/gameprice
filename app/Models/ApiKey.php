<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApiKey extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'name',
        'plan',
        'requests_limit_daily',
        'requests_count_today',
        'rate_limit_per_minute',
        'is_active',
        'expires_at',
        'last_used_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'expires_at' => 'datetime',
        'last_used_at' => 'datetime',
    ];

    /**
     * Plan configuration with limits.
     */
    public static function planConfig(string $plan): array
    {
        return match ($plan) {
            'free' => [
                'requests_limit_daily' => 100,
                'rate_limit_per_minute' => 10,
                'cache_ttl_minutes' => 30,
                'webhooks' => false,
                'support' => 'community',
            ],
            'basic' => [
                'requests_limit_daily' => 10_000,
                'rate_limit_per_minute' => 60,
                'cache_ttl_minutes' => 30,
                'webhooks' => false,
                'support' => 'email',
            ],
            'pro' => [
                'requests_limit_daily' => 100_000,
                'rate_limit_per_minute' => 120,
                'cache_ttl_minutes' => 15,
                'webhooks' => true,
                'support' => 'priority',
            ],
            'ultra' => [
                'requests_limit_daily' => 0, // unlimited
                'rate_limit_per_minute' => 300,
                'cache_ttl_minutes' => 5,
                'webhooks' => true,
                'support' => 'dedicated',
            ],
            default => [
                'requests_limit_daily' => 100,
                'rate_limit_per_minute' => 10,
                'cache_ttl_minutes' => 30,
                'webhooks' => false,
                'support' => 'community',
            ],
        };
    }

    /**
     * Check if the key has exceeded its daily limit.
     */
    public function hasExceededDailyLimit(): bool
    {
        if ($this->plan === 'ultra') {
            return false;
        }

        return $this->requests_count_today >= $this->requests_limit_daily;
    }

    /**
     * Increment the daily request counter.
     */
    public function incrementUsage(): void
    {
        $this->increment('requests_count_today');
        $this->update(['last_used_at' => now()]);
    }

    /**
     * Reset daily counters (should be called by a daily scheduled command).
     */
    public static function resetDailyCounters(): void
    {
        self::query()->update(['requests_count_today' => 0]);
    }

    /**
     * Generate a new secure API key.
     */
    public static function generate(string $plan = 'free', ?string $name = null, ?int $expiresDays = null): self
    {
        $config = self::planConfig($plan);

        return self::create([
            'key' => hash('sha256', uniqid('gp_', true) . random_bytes(32)),
            'name' => $name,
            'plan' => $plan,
            'requests_limit_daily' => $config['requests_limit_daily'],
            'rate_limit_per_minute' => $config['rate_limit_per_minute'],
            'is_active' => true,
            'expires_at' => $expiresDays ? now()->addDays($expiresDays) : null,
        ]);
    }
}
