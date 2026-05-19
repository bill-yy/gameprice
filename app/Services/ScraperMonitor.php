<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ScraperMonitor
{
    private const PREFIX = 'scraper_health:';
    private const TTL_HOURS = 24;

    /**
     * Record a successful scrape
     */
    public static function recordSuccess(string $store, int $resultsCount): void
    {
        $key = self::PREFIX . $store;
        $data = Cache::get($key, [
            'successes' => 0,
            'failures' => 0,
            'last_success' => null,
            'last_failure' => null,
            'last_result_count' => 0,
        ]);

        $data['successes']++;
        $data['last_success'] = now()->toIso8601String();
        $data['last_result_count'] = $resultsCount;

        Cache::put($key, $data, now()->addHours(self::TTL_HOURS));
    }

    /**
     * Record a failed scrape
     */
    public static function recordFailure(string $store, string $error): void
    {
        $key = self::PREFIX . $store;
        $data = Cache::get($key, [
            'successes' => 0,
            'failures' => 0,
            'last_success' => null,
            'last_failure' => null,
            'last_result_count' => 0,
        ]);

        $data['failures']++;
        $data['last_failure'] = now()->toIso8601String();

        Cache::put($key, $data, now()->addHours(self::TTL_HOURS));

        // Log if failures are accumulating
        if ($data['failures'] >= 3 && $data['failures'] % 3 === 0) {
            Log::warning("Scraper '{$store}' has failed {$data['failures']} times recently", [
                'store' => $store,
                'error' => $error,
            ]);
        }
    }

    /**
     * Get health status for all stores
     */
    public static function getHealth(array $stores): array
    {
        $health = [];

        foreach ($stores as $store) {
            $key = self::PREFIX . $store;
            $data = Cache::get($key, [
                'successes' => 0,
                'failures' => 0,
                'last_success' => null,
                'last_failure' => null,
                'last_result_count' => 0,
            ]);

            $total = $data['successes'] + $data['failures'];
            $successRate = $total > 0 ? round(($data['successes'] / $total) * 100, 1) : 100;

            // Determine status
            if ($data['failures'] >= 5 && $data['successes'] === 0) {
                $status = 'down';
            } elseif ($data['failures'] >= 3) {
                $status = 'degraded';
            } elseif ($data['last_failure'] && $data['last_success'] && $data['last_failure'] > $data['last_success']) {
                $status = 'degraded';
            } else {
                $status = 'up';
            }

            $health[$store] = [
                'status' => $status,
                'success_rate' => $successRate,
                'successes' => $data['successes'],
                'failures' => $data['failures'],
                'last_success' => $data['last_success'],
                'last_failure' => $data['last_failure'],
                'last_result_count' => $data['last_result_count'],
            ];
        }

        return $health;
    }

    /**
     * Check if any store is down and should be alerted
     */
    public static function getAlerts(array $stores): array
    {
        $health = self::getHealth($stores);
        $alerts = [];

        foreach ($health as $store => $data) {
            if ($data['status'] === 'down') {
                $alerts[] = [
                    'store' => $store,
                    'severity' => 'critical',
                    'message' => "Store '{$store}' is down — no successful scrapes recorded",
                ];
            } elseif ($data['status'] === 'degraded' && $data['failures'] >= 5) {
                $alerts[] = [
                    'store' => $store,
                    'severity' => 'warning',
                    'message' => "Store '{$store}' is degraded — {$data['failures']} recent failures",
                ];
            }
        }

        return $alerts;
    }

    /**
     * Reset health data for a store
     */
    public static function reset(string $store): void
    {
        Cache::forget(self::PREFIX . $store);
    }
}
