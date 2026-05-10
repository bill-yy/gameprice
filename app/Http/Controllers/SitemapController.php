<?php

namespace App\Http\Controllers;

use App\Models\Game;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

class SitemapController extends Controller
{
    public function index(): Response
    {
        $xml = Cache::remember('sitemap.xml', 3600, function () {
            $games = Game::where('is_active', true)
                ->orderBy('updated_at', 'desc')
                ->select('slug', 'updated_at')
                ->get();

            $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
            $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

            // Home
            $xml .= '  <url>' . "\n";
            $xml .= '    <loc>' . url('/') . '</loc>' . "\n";
            $xml .= '    <changefreq>daily</changefreq>' . "\n";
            $xml .= '    <priority>1.0</priority>' . "\n";
            $xml .= '  </url>' . "\n";

            foreach ($games as $game) {
                $xml .= '  <url>' . "\n";
                $xml .= '    <loc>' . route('game.show', $game->slug) . '</loc>' . "\n";
                $xml .= '    <lastmod>' . $game->updated_at->toDateString() . '</lastmod>' . "\n";
                $xml .= '    <changefreq>daily</changefreq>' . "\n";
                $xml .= '    <priority>0.8</priority>' . "\n";
                $xml .= '  </url>' . "\n";
            }

            $xml .= '</urlset>';

            return $xml;
        });

        return response($xml, 200, [
            'Content-Type' => 'application/xml',
        ]);
    }
}
