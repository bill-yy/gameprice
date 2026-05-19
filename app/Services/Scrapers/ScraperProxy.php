<?php

namespace App\Services\Scrapers;

use Illuminate\Support\Facades\Http;

/**
 * Helper to route scraper requests through a Cloudflare Worker proxy.
 * This bypasses Cloudflare blocks on datacenter IPs (like Dokploy).
 *
 * Set CLOUDFLARE_WORKER_URL in your .env to enable.
 * The worker should accept ?url= parameter and forward requests.
 */
class ScraperProxy
{
    private static ?string $workerUrl = null;

    public static function getWorkerUrl(): ?string
    {
        if (self::$workerUrl === null) {
            self::$workerUrl = config('services.cloudflare.worker_url')
                ?? env('CLOUDFLARE_WORKER_URL')
                ?? null;
        }

        return self::$workerUrl;
    }

    public static function isEnabled(): bool
    {
        return !empty(self::getWorkerUrl());
    }

    /**
     * Build a request through the proxy if enabled, or direct otherwise.
     */
    public static function request(string $method, string $targetUrl, array $options = []): \Illuminate\Http\Client\PendingRequest
    {
        $workerUrl = self::getWorkerUrl();

        $headers = array_merge([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
            'Accept' => 'application/json, text/html, */*',
            'Accept-Language' => 'en-US,en;q=0.9',
        ], $options['headers'] ?? []);

        if ($workerUrl) {
            $proxyUrl = rtrim($workerUrl, '/') . '/?url=' . urlencode($targetUrl);

            $req = Http::withHeaders($headers)->timeout($options['timeout'] ?? 30);

            if ($method === 'POST') {
                return $req->withBody(json_encode($options['body'] ?? []), 'application/json');
            }

            return $req;
        }

        // Direct request
        $req = Http::withHeaders($headers)->timeout($options['timeout'] ?? 30);

        if (isset($options['body']) && $method === 'POST') {
            $req = $req->withBody(json_encode($options['body']), 'application/json');
        }

        return $req;
    }

    /**
     * Execute a GET request (proxy or direct).
     */
    public static function get(string $url, array $query = [], array $options = []): \Illuminate\Http\Client\Response
    {
        $workerUrl = self::getWorkerUrl();

        if ($workerUrl) {
            $proxyUrl = rtrim($workerUrl, '/') . '/?url=' . urlencode($url . (empty($query) ? '' : '?' . http_build_query($query)));
            return Http::withHeaders(array_merge([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            ], $options['headers'] ?? []))
                ->timeout($options['timeout'] ?? 30)
                ->get($proxyUrl);
        }

        return Http::withHeaders(array_merge([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        ], $options['headers'] ?? []))
            ->timeout($options['timeout'] ?? 30)
            ->get($url, $query);
    }

    /**
     * Execute a POST request (proxy or direct).
     */
    public static function post(string $url, array $body = [], array $options = []): \Illuminate\Http\Client\Response
    {
        $workerUrl = self::getWorkerUrl();

        if ($workerUrl) {
            $proxyUrl = rtrim($workerUrl, '/') . '/?url=' . urlencode($url);
            return Http::withHeaders(array_merge([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                'Content-Type' => 'application/json',
            ], $options['headers'] ?? []))
                ->timeout($options['timeout'] ?? 30)
                ->post($proxyUrl, $body);
        }

        return Http::withHeaders(array_merge([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        ], $options['headers'] ?? []))
            ->timeout($options['timeout'] ?? 30)
            ->post($url, $body);
    }
}
