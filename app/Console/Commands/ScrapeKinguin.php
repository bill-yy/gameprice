<?php

namespace App\Console\Commands;

use App\Models\Game;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ScrapeKinguin extends Command
{
    protected $signature = 'kinguin:scrape {--limit=50 : Max games to process}';

    protected $description = 'Scrape real prices from Kinguin search results';

    public function handle(): int
    {
        $store = Store::firstOrCreate(
            ['slug' => 'kinguin'],
            [
                'name' => 'Kinguin',
                'website' => 'https://www.kinguin.net',
                'logo_url' => 'https://www.kinguin.net/favicon.ico',
                'is_active' => true,
                'is_official' => false,
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
            $this->warn('No games found without Kinguin prices.');
            return self::SUCCESS;
        }

        $results = [];
        $bar = $this->output->createProgressBar($games->count());

        foreach ($games as $game) {
            $bar->advance();

            try {
                $searchResults = $this->searchKinguin($game->title);

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
                Log::warning('Kinguin scrape failed', [
                    'game' => $game->title,
                    'error' => $e->getMessage(),
                ]);
            }

            usleep(1000000);
        }

        $bar->finish();
        $this->newLine();

        if (!empty($results)) {
            $path = base_path('data/kinguin_prices.json');
            file_put_contents($path, json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $this->info('Kinguin: saved ' . count($results) . ' prices to data/kinguin_prices.json');

            foreach ($results as $result) {
                $game = Game::where('title', $result['game_title'])->first();
                if ($game) {
                    Product::updateOrCreate(
                        ['game_id' => $game->id, 'store_id' => $store->id],
                        [
                            'current_price' => $result['price_eur'],
                            'original_price' => $result['original_price_eur'],
                            'discount_percent' => $result['discount_percent'],
                            'url' => $result['url'],
                            'affiliate_url' => $result['url'],
                            'is_real_price' => true,
                            'currency' => 'EUR',
                            'platform' => 'PC',
                            'region' => $result['region'] ?? 'global',
                            'type' => 'key',
                            'in_stock' => $result['in_stock'],
                        ]
                    );
                }
            }
            Cache::flush();
        } else {
            $this->warn('Kinguin: no prices found.');
        }

        return self::SUCCESS;
    }

    private function searchKinguin(string $query): array
    {
        $response = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Accept' => 'application/json',
            'Accept-Language' => 'en-US,en;q=0.9',
            'Referer' => 'https://www.kinguin.net/',
            'Origin' => 'https://www.kinguin.net',
        ])->timeout(30)->get('https://www.kinguin.net/svc/search/api/v1/products', [
            'q' => $query,
            'limit' => 24,
            'sort' => 'score',
        ]);

        if (!$response->successful()) {
            Log::debug('Kinguin: HTTP ' . $response->status() . ' for query: ' . $query);
            return [];
        }

        $data = $response->json();

        return $this->parseProducts($data, $query);
    }

    private function parseProducts(array $data, string $query): array
    {
        $products = [];

        $items = $data['data']['items']
            ?? $data['data']['products']
            ?? $data['items']
            ?? $data['products']
            ?? [];

        if (!is_array($items)) {
            return [];
        }

        foreach ($items as $item) {
            $name = $item['name'] ?? $item['title'] ?? null;
            if (!$name) {
                continue;
            }

            $price = $item['price'] ?? $item['currentPrice'] ?? $item['minPrice'] ?? null;
            if (is_array($price)) {
                $price = $price['amount'] ?? $price['value'] ?? $price['eur'] ?? null;
            }
            if ($price === null) {
                continue;
            }
            $price = (float) $price;

            $originalPrice = $item['originalPrice'] ?? $item['basePrice'] ?? $item['msrp'] ?? $item['marketPrice'] ?? null;
            if (is_array($originalPrice)) {
                $originalPrice = $originalPrice['amount'] ?? $originalPrice['value'] ?? $originalPrice['eur'] ?? null;
            }
            $originalPrice = $originalPrice !== null ? (float) $originalPrice : $price;

            $discount = $item['discount'] ?? $item['discountPercent'] ?? null;
            if ($discount === null && $originalPrice > 0 && $originalPrice > $price) {
                $discount = (int) round((1 - $price / $originalPrice) * 100);
            }
            $discount = (int) ($discount ?? 0);

            $slug = $item['slug'] ?? $item['id'] ?? null;
            $url = $slug ? "https://www.kinguin.net/en/catalogsearch/result?q=" . urlencode($query) . "&p=1&mv=0&sort=default&manufacturers=&price_min=&price_max=&platforms=&tags=&release_date=&include=&exclude=&hide=out-of-stock" : ($item['url'] ?? "https://www.kinguin.net/en/catalogsearch/result?q=" . urlencode($query));

            if (isset($item['url']) && is_string($item['url'])) {
                $url = $item['url'];
            } elseif ($slug) {
                $url = "https://www.kinguin.net/en/product/{$slug}";
            }

            $region = 'global';
            if (isset($item['region']) && is_string($item['region'])) {
                $region = strtolower($item['region']);
            } elseif (isset($item['regions']) && is_array($item['regions'])) {
                $regionNames = array_map('strtolower', $item['regions']);
                if (in_array('global', $regionNames)) {
                    $region = 'global';
                } elseif (!empty($regionNames)) {
                    $region = $regionNames[0];
                }
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
