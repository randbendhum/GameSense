<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

Artisan::command('gamesense:clear-cache', function (): void {
    Cache::flush();
    $this->info('[GAMESENSE AI] Tactical response cache cleared successfully.');
})->purpose('Clears the GameSense AI tactical cache in MySQL');
