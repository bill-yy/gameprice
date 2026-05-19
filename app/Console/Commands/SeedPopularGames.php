<?php

namespace App\Console\Commands;

use App\Models\Game;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class SeedPopularGames extends Command
{
    protected $signature = 'games:seed-popular {--force : Skip confirmation}';

    protected $description = 'Seed popular Steam games if the games table is empty';

    public function handle(): int
    {
        $count = Game::count();

        if ($count > 0 && ! $this->option('force')) {
            $this->warn("There are already {$count} games in the database. Use --force to seed anyway.");

            return self::SUCCESS;
        }

        $games = [
            ['steam_app_id' => 1091500, 'title' => 'Cyberpunk 2077', 'slug' => 'cyberpunk-2077'],
            ['steam_app_id' => 1245620, 'title' => 'Elden Ring', 'slug' => 'elden-ring'],
            ['steam_app_id' => 292030, 'title' => 'The Witcher 3: Wild Hunt', 'slug' => 'the-witcher-3-wild-hunt'],
            ['steam_app_id' => 1174180, 'title' => 'Red Dead Redemption 2', 'slug' => 'red-dead-redemption-2'],
            ['steam_app_id' => 271590, 'title' => 'Grand Theft Auto V', 'slug' => 'grand-theft-auto-v'],
            ['steam_app_id' => 1085660, 'title' => 'Destiny 2', 'slug' => 'destiny-2'],
            ['steam_app_id' => 990080, 'title' => "Hogwarts Legacy", 'slug' => 'hogwarts-legacy'],
            ['steam_app_id' => 813780, 'title' => 'Age of Empires IV', 'slug' => 'age-of-empires-iv'],
            ['steam_app_id' => 578080, 'title' => "PLAYERUNKNOWN'S BATTLEGROUNDS", 'slug' => 'playerunknowns-battlegrounds'],
            ['steam_app_id' => 730, 'title' => 'Counter-Strike 2', 'slug' => 'counter-strike-2'],
            ['steam_app_id' => 252490, 'title' => 'Rust', 'slug' => 'rust'],
            ['steam_app_id' => 1086940, 'title' => "Baldur's Gate 3", 'slug' => 'baldurs-gate-3'],
            ['steam_app_id' => 377160, 'title' => 'Fallout 4', 'slug' => 'fallout-4'],
            ['steam_app_id' => 489830, 'title' => 'The Elder Scrolls V: Skyrim Special Edition', 'slug' => 'the-elder-scrolls-v-skyrim-special-edition'],
            ['steam_app_id' => 413150, 'title' => 'Stardew Valley', 'slug' => 'stardew-valley'],
            ['steam_app_id' => 105600, 'title' => 'Terraria', 'slug' => 'terraria'],
            ['steam_app_id' => 275850, 'title' => 'No Man\'s Sky', 'slug' => 'no-mans-sky'],
            ['steam_app_id' => 620, 'title' => 'Portal 2', 'slug' => 'portal-2'],
            ['steam_app_id' => 236850, 'title' => 'Europa Universalis IV', 'slug' => 'europa-universalis-iv'],
            ['steam_app_id' => 289070, 'title' => 'Sid Meier\'s Civilization VI', 'slug' => 'sid-meiers-civilization-vi'],
            ['steam_app_id' => 359550, 'title' => "Tom Clancy's Rainbow Six Siege", 'slug' => 'tom-clancys-rainbow-six-siege'],
            ['steam_app_id' => 1172470, 'title' => 'Apex Legends', 'slug' => 'apex-legends'],
            ['steam_app_id' => 1151640, 'title' => 'Horizon Zero Dawn Remastered', 'slug' => 'horizon-zero-dawn-remastered'],
            ['steam_app_id' => 1551360, 'title' => 'Forza Horizon 5', 'slug' => 'forza-horizon-5'],
            ['steam_app_id' => 1196590, 'title' => 'Resident Evil Village', 'slug' => 'resident-evil-village'],
            ['steam_app_id' => 1601580, 'title' => 'Frostpunk 2', 'slug' => 'frostpunk-2'],
            ['steam_app_id' => 1962660, 'title' => 'Call of Duty', 'slug' => 'call-of-duty'],
            ['steam_app_id' => 2344520, 'title' => 'Diablo IV', 'slug' => 'diablo-iv'],
            ['steam_app_id' => 238960, 'title' => 'Path of Exile', 'slug' => 'path-of-exile'],
            ['steam_app_id' => 550, 'title' => 'Left 4 Dead 2', 'slug' => 'left-4-dead-2'],
            ['steam_app_id' => 70, 'title' => 'Half-Life', 'slug' => 'half-life'],
            ['steam_app_id' => 220, 'title' => 'Half-Life 2', 'slug' => 'half-life-2'],
            ['steam_app_id' => 400, 'title' => 'Portal', 'slug' => 'portal'],
            ['steam_app_id' => 8930, 'title' => "Sid Meier's Civilization V", 'slug' => 'sid-meiers-civilization-v'],
            ['steam_app_id' => 200260, 'title' => 'Batman: Arkham City', 'slug' => 'batman-arkham-city'],
            ['steam_app_id' => 209000, 'title' => 'Batman: Arkham Origins', 'slug' => 'batman-arkham-origins'],
            ['steam_app_id' => 208650, 'title' => 'Batman: Arkham Knight', 'slug' => 'batman-arkham-knight'],
            ['steam_app_id' => 200510, 'title' => 'XCOM: Enemy Unknown', 'slug' => 'xcom-enemy-unknown'],
            ['steam_app_id' => 268500, 'title' => 'XCOM 2', 'slug' => 'xcom-2'],
            ['steam_app_id' => 8500, 'title' => 'EVE Online', 'slug' => 'eve-online'],
            ['steam_app_id' => 323190, 'title' => 'Frostpunk', 'slug' => 'frostpunk'],
            ['steam_app_id' => 1286680, 'title' => 'Tiny Tina\'s Wonderlands', 'slug' => 'tiny-tinas-wonderlands'],
            ['steam_app_id' => 397540, 'title' => 'Borderlands 3', 'slug' => 'borderlands-3'],
            ['steam_app_id' => 49520, 'title' => 'Borderlands 2', 'slug' => 'borderlands-2'],
            ['steam_app_id' => 1286830, 'title' => 'STAR WARS Jedi: Survivor', 'slug' => 'star-wars-jedi-survivor'],
            ['steam_app_id' => 1237970, 'title' => 'Titanfall 2', 'slug' => 'titanfall-2'],
            ['steam_app_id' => 1238840, 'title' => 'Battlefield 1', 'slug' => 'battlefield-1'],
            ['steam_app_id' => 1517290, 'title' => 'Battlefield 2042', 'slug' => 'battlefield-2042'],
            ['steam_app_id' => 1811260, 'title' => 'EA SPORTS FC 24', 'slug' => 'ea-sports-fc-24'],
            ['steam_app_id' => 2195250, 'title' => 'EA SPORTS FC 25', 'slug' => 'ea-sports-fc-25'],
        ];

        $created = 0;
        foreach ($games as $game) {
            $existing = Game::where('steam_app_id', $game['steam_app_id'])->first();
            if ($existing) {
                continue;
            }

            Game::create([
                'slug' => $game['slug'],
                'title' => $game['title'],
                'steam_app_id' => $game['steam_app_id'],
                'description' => null,
                'release_date' => null,
                'cover_image' => null,
                'platforms' => ['windows'],
                'genres' => [],
                'developer' => null,
                'publisher' => null,
                'metacritic_score' => null,
                'is_active' => true,
            ]);
            $created++;
        }

        $this->info("Created {$created} popular games.");

        return self::SUCCESS;
    }
}
