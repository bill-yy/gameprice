<?php

namespace App\Jobs;

use App\Models\Game;
use App\Services\Steam\SteamStoreService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class FetchSteamGameDetails implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Game $game) {}

    public function handle(SteamStoreService $steamService): void
    {
        $steamService->updateGameFromSteam($this->game);
    }
}
