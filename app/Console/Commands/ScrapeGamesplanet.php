<?php

namespace App\Console\Commands;

use App\Models\Game;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ScrapeGamesplanet extends Command
{
    protected $signature = 'gamesplanet:scrape {--limit=50 : Max games to process}';

    protected $description = 'Scrape real prices from Gamesplanet search results';

    public function handle(): int
    {
        $store = Store::firstOrCreate(
            ['slug' => 'gamesplanet'],
            [
                'name' => 'Gamesplanet',
                'website' => 'https://www.gamesplanet.com',
                'logo_url' => 'https://www.gamesplanet.com/favicon.ico',
                'is_active' => true,
                'is_official' => true,
            ]
        );

        $existingGameIds = Product::where('store_id', $store->id)
            ->pluck('game_id')
            ->toArray();

        $games = Game::where('is_active', true)
            ->whereNotNull('title')
            ->when(!empty($existingGameIds), fn($q) => $q->whereNotIn('id', $existingGameIds))
            ->orderByDesc('metacritic_score')
            ->limit($this->option('limit'))
            ->get();

        if ($games->isEmpty()) {
            $this->warn('No games found without Gamesplanet prices.');
            return self::SUCCESS;
        }

        $results = [];
        $bar = $this->output->createProgressBar($games->count());

        foreach ($games as $game) {
            $bar->advance();

            try {
                $searchResults = $this->searchGamesplanet($game->title);

                if (empty($searchResults)) {
                    continue;
                }

                $bestMatch = $this->findBestMatch($searchResults, $game->title);

                if (!$bestMatch) {
                    continue;
                }

                $results[] = [
                    'name' => $bestMatch['name'],
                    'game_title' => $game->title,
                    'price_eur' => $bestMatch['price_eur'],
                    'original_price_eur' => $bestMatch['original_price_eur'],
                    'discount_percent' => $bestMatch['discount_percent'],
                    'url' => $bestMatch['url'],
                    'region' => $bestMatch['region'],
                    'in_stock' => $bestMatch['in_stock'],
                ];
            } catch (\Throwable $e) {
                Log::warning('Gamesplanet scrape failed', [
                    'game' => $game->title,
                    'error' => $e->getMessage(),
                ]);
            }

            usleep(1000000);
        }

        $bar->finish();
        $this->newLine();

        if (!empty($results)) {
            $path = base_path('data/gamesplanet_prices.json');
            file_put_contents($path, json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $this->info('Gamesplanet: saved ' . count($results) . ' prices to data/gamesplanet_prices.json');
        } else {
            $this->warn('Gamesplanet: no prices found.');
        }

        return self::SUCCESS;
    }

    private function searchGamesplanet(string $query): array
    {
        $response = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Accept' => 'application/json',
            'Accept-Language' => 'en-US,en;q=0.9',
            'Referer' => 'https://us.gamesplanet.com/',
        ])->timeout(30)->get('https://us.gamesplanet.com/api/products/search', [
            'q' => $query,
        ]);

        if (!$response->successful()) {
            Log::debug('Gamesplanet: HTTP ' . $response->status() . ' for query: ' . $query);
            return [];
        }

        $data = $response->json();

        return $this->parseProducts($data, $query);
    }

    private function parseProducts(array $data, string $query): array
    {
        $products = [];

        $items = $data['products']
            ?? $data['data']
            ?? $data['items']
            ?? $data['results']
            ?? [];

        if (!is_array($items)) {
            return [];
        }

        foreach ($items as $item) {
            $name = $item['name'] ?? $item['title'] ?? null;
            if (!$name) {
                continue;
            }

            $price = $item['price'] ?? $item['currentPrice'] ?? $item['final_price'] ?? null;
            if (is_array($price)) {
                $price = $price['amount'] ?? $price['value'] ?? $price['eur'] ?? null;
            }
            if ($price === null) {
                continue;
            }
            $price = (float) $price;

            $originalPrice = $item['originalPrice'] ?? $item['basePrice'] ?? $item['msrp'] ?? $item['regular_price'] ?? null;
            if (is_array($originalPrice)) {
                $originalPrice = $originalPrice['amount'] ?? $originalPrice['value'] ?? $originalPrice['eur'] ?? null;
            }
            $originalPrice = $originalPrice !== null ? (float) $originalPrice : $price;

            $discount = $item['discount'] ?? $item['discountPercent'] ?? $item['discount_percent'] ?? null;
            if ($discount === null && $originalPrice > 0 && $originalPrice > $price) {
                $discount = (int) round((1 - $price / $originalPrice) * 100);
            }
            $discount = (int) ($discount ?? 0);

            $slug = $item['slug'] ?? $item['id'] ?? null;
            $url = $item['url'] ?? null;
            if (!$url && $slug) {
                $url = "https://us.gamesplanet.com/product/{$slug}";
            }
            if (!$url) {
                $url = "https://us.gamesplanet.com/search?query=" . urlencode($query);
            }

            $region = 'global';
            if (isset($item['region']) && is_string($item['region'])) {
                $region = strtolower($item['region']);
            } elseif (isset($item['territory']) && is_string($item['territory'])) {
                $region = strtolower($item['territory']);
            }

            $products[] = [
                'name' => $name,
                'price_eur' => $price,
                'original_price_eur' => $originalPrice,
                'discount_percent' => $discount,
                'url' => $url,
                'region' => $region,
                'in_stock' => $item['inStock'] ?? $item['available'] ?? $item['isAvailable'] ?? true,
            ];
        }

        return $products;
    }

    private function findBestMatch(array $results, string $gameTitle): ?array
    {
        usort($results, function ($a, $b) use ($gameTitle) {
            $aScore = 0;
            $bScore = 0;

            similar_text(strtolower($a['name']), strtolower($gameTitle), $aSim);
            similar_text(strtolower($b['name']), strtolower($gameTitle), $bSim);
            $aScore += $aSim;
            $bScore += $bSim;

            if (stripos($a['name'], $gameTitle) !== false) {
                $aScore += 20;
            }
            if (stripos($b['name'], $gameTitle) !== false) {
                $bScore += 20;
            }

            if (stripos($a['name'], 'Steam') !== false || stripos($a['name'], 'PC') !== false) {
                $aScore += 5;
            }
            if (stripos($b['name'], 'Steam') !== false || stripos($b['name'], 'PC') !== false) {
                $bScore += 5;
            }

            if ($a['region'] === 'global') {
                $aScore += 3;
            }
            if ($b['region'] === 'global') {
                $bScore += 3;
            }

            return $bScore <=> $aScore;
        });

        return $results[0] ?? null;
    }
}
