<?php

namespace App\Console\Commands;

use App\Models\Game;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ScrapeG2A extends Command
{
    protected $signature = 'g2a:scrape {--limit=50 : Max games to process}';

    protected $description = 'Scrape real prices from G2A using their search API';

    public function handle(): int
    {
        $games = Game::where('is_active', true)
            ->whereNotNull('title')
            ->orderByDesc('metacritic_score')
            ->limit($this->option('limit'))
            ->get();

        if ($games->isEmpty()) {
            $this->warn('No games found.');
            return self::SUCCESS;
        }

        $results = [];
        $bar = $this->output->createProgressBar($games->count());

        foreach ($games as $game) {
            $bar->advance();

            try {
                $searchResults = $this->searchG2A($game->title);

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
                Log::warning('G2A scrape failed', [
                    'game' => $game->title,
                    'error' => $e->getMessage(),
                ]);
            }

            usleep(1000000);
        }

        $bar->finish();
        $this->newLine();

        if (!empty($results)) {
            $path = base_path('data/g2a_prices.json');
            file_put_contents($path, json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $this->info('G2A: saved ' . count($results) . ' prices to data/g2a_prices.json');
        } else {
            $this->warn('G2A: no prices found.');
        }

        return self::SUCCESS;
    }

    private function searchG2A(string $query): array
    {
        $response = Http::withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Accept' => 'application/json',
            'Accept-Language' => 'en-US,en;q=0.9',
            'Referer' => 'https://www.g2a.com/',
            'Origin' => 'https://www.g2a.com',
        ])->timeout(30)->post('https://www.g2a.com/search/api/v3/products', [
            'itemsPerPage' => 24,
            'include' => 'categories,categoryTree,media,regions,attributes,developerName,publisherName,discount',
            'updatedAfter' => null,
            'sort' => 'score',
            'isWholesale' => false,
            'funnel' => 'r',
            'phrase' => $query,
        ]);

        if (!$response->successful()) {
            Log::debug('G2A: HTTP ' . $response->status() . ' for query: ' . $query);
            return [];
        }

        $data = $response->json();

        return $this->parseProducts($data, $query);
    }

    private function parseProducts(array $data, string $query): array
    {
        $products = [];

        $items = $data['data']['products']
            ?? $data['products']
            ?? $data['data']['items']
            ?? $data['items']
            ?? [];

        if (isset($items['hits'])) {
            $items = $items['hits'];
        }
        if (isset($items['edges'])) {
            $items = array_column($items, 'node');
        }

        if (!is_array($items) || !array_is_list($items)) {
            return [];
        }

        foreach ($items as $item) {
            $name = $item['name'] ?? $item['title'] ?? null;
            if (!$name) {
                continue;
            }

            $price = $item['minPrice'] ?? $item['price'] ?? $item['currentPrice'] ?? null;
            if (is_array($price)) {
                $price = $price['amount'] ?? $price['value'] ?? null;
            }
            if ($price === null) {
                continue;
            }
            $price = (float) $price;

            $originalPrice = $item['maxPrice'] ?? $item['originalPrice'] ?? $item['basePrice'] ?? $item['msrp'] ?? null;
            if (is_array($originalPrice)) {
                $originalPrice = $originalPrice['amount'] ?? $originalPrice['value'] ?? null;
            }
            $originalPrice = $originalPrice !== null ? (float) $originalPrice : $price;

            $discount = $item['discount'] ?? $item['discountPercent'] ?? null;
            if ($discount === null && $originalPrice > 0 && $originalPrice > $price) {
                $discount = (int) round((1 - $price / $originalPrice) * 100);
            }
            $discount = (int) ($discount ?? 0);

            $slug = $item['slug'] ?? $item['id'] ?? null;
            $url = $slug ? "https://www.g2a.com/{$slug}" : ($item['url'] ?? "https://www.g2a.com/search?query=" . urlencode($query));

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
                'in_stock' => $item['inStock'] ?? $item['available'] ?? true,
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
