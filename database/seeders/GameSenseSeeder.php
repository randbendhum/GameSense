<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Jobs\GenerateTacticalAdviceJob;
use App\Models\Category;
use App\Models\Game;
use App\Models\PromptHistory;
use App\Services\TacticalPromptHasher;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class GameSenseSeeder extends Seeder
{
    public function run(): void
    {
        $hasher = app(TacticalPromptHasher::class);

        $gamesData = [
            [
                'slug' => 'victoria-3',
                'title' => 'Victoria 3',
                'short_code' => 'VIC-03',
                'genre' => 'Grand Strategy',
                'engine_name' => 'Clausewitz Engine',
                'accent_color' => '#FFDE59',
                'default_resources' => ['Coal & Iron', 'Tools & Steel', 'Railway Infrastructure', 'Taxation Capacity'],
                'system_prompt_context' => 'Focus on Construction Sectors, Production Methods (PMs), market price balance (-75% to +75%), Pop qualifications, Interest Groups (Industrialists/Trade Unions), and Fiscal Balance.',
                'is_active' => true,
            ],
            [
                'slug' => 'hollow-knight',
                'title' => 'Hollow Knight',
                'short_code' => 'HK-01',
                'genre' => 'Metroidvania',
                'engine_name' => 'Unity 2D',
                'accent_color' => '#00FF66',
                'default_resources' => ['Soul & Masks', 'Charm Notches', 'Pale Ore Upgrades', 'Geo & Essence'],
                'system_prompt_context' => 'Focus on Charm synergies, invulnerability frames (Descending Dark / Shade Cloak), pogo-strikes, Soul economy in the Pantheon of Hallownest, and Boss attack patterns.',
                'is_active' => true,
            ],
            [
                'slug' => 'farming-simulator-25',
                'title' => 'Farming Simulator 25',
                'short_code' => 'FS-25',
                'genre' => 'Agro Simulation',
                'engine_name' => 'GIANTS Engine 10',
                'accent_color' => '#FF5722',
                'default_resources' => ['Cash Liquidity ($)', 'Yield per Hectare', 'Silage / BGA Chain', 'Lime & Fertilizer'],
                'system_prompt_context' => 'Focus on precision farming, industrial production chains, seasonal price calendar, equipment leasing vs. purchasing, and AI worker/GPS optimization.',
                'is_active' => true,
            ],
            [
                'slug' => 'baldurs-gate-3',
                'title' => "Baldur's Gate 3",
                'short_code' => 'BG-03',
                'genre' => 'Tactical CRPG',
                'engine_name' => 'Divinity Engine 4.0',
                'accent_color' => '#FFDE59',
                'default_resources' => ['Spell Slots', 'Action Economy (Action Surge)', 'Camp Supplies', 'Sorcery / Ki Points'],
                'system_prompt_context' => 'Focus on Honour Mode, Boss Legendary Actions, Action Economy, surface combos (Wet + Lightning/Cold), Arcane Acuity stacks, and tactical high-ground positioning.',
                'is_active' => true,
            ],
        ];

        $games = [];
        foreach ($gamesData as $gameItem) {
            $games[$gameItem['slug']] = Game::updateOrCreate(
                ['slug' => $gameItem['slug']],
                $gameItem
            );
        }

        $categoriesData = [
            [
                'slug' => 'economy-resources',
                'name' => 'Economy & Resources',
                'code_tag' => 'ECO-01',
                'description' => 'Deficit resolution, input bottlenecks, and ROI maximization.',
                'prompt_directive' => 'Prioritize input equilibrium, operational cost reduction, and measurable ROI.',
            ],
            [
                'slug' => 'builds-loadouts',
                'name' => 'Builds & Loadouts',
                'code_tag' => 'BLD-02',
                'description' => 'Attribute synergies, charms, feats, and meta equipment loadouts.',
                'prompt_directive' => 'Specify exact item combinations, talents, and damage/efficiency multipliers.',
            ],
            [
                'slug' => 'combat-bosses',
                'name' => 'Combat & Bosses',
                'code_tag' => 'CMB-03',
                'description' => 'Boss attack pattern analysis, i-frame windows, and crowd control.',
                'prompt_directive' => 'Detail punish windows, safe positioning, and party-wipe mitigation.',
            ],
            [
                'slug' => 'logistics-automation',
                'name' => 'Logistics & Automation',
                'code_tag' => 'LOG-04',
                'description' => 'Production chains, transport infrastructure, and scaling throughput.',
                'prompt_directive' => 'Optimize flow between extraction, processing, and distribution without bottlenecks.',
            ],
        ];

        $categories = [];
        foreach ($categoriesData as $catItem) {
            $categories[$catItem['slug']] = Category::updateOrCreate(
                ['slug' => $catItem['slug']],
                $catItem
            );
        }

        // Pre-populate 4 cached tactical analyses for instant (< 5ms) Cache Hit demonstration
        $seedScenarios = [
            [
                'game' => $games['victoria-3'],
                'category' => $categories['economy-resources'],
                'game_phase' => 'Mid Game (Industrialization / Act 2)',
                'missing_resource' => 'Coal & Iron (+58% above base price)',
                'user_query' => 'My Construction Sectors entered a severe deficit after switching to Iron-Frame Buildings. Coal and Iron prices skyrocketed and my gold reserves will hit zero in 10 weeks.',
                'hit_count' => 14,
            ],
            [
                'game' => $games['hollow-knight'],
                'category' => $categories['combat-bosses'],
                'game_phase' => 'Late Game (Endgame / Pantheon / Honour)',
                'missing_resource' => 'Soul & Healing Windows',
                'user_query' => 'I keep losing masks against Watcher Knights and Pure Vessel because I cannot find safe windows to cast Focus without taking double damage.',
                'hit_count' => 9,
            ],
            [
                'game' => $games['farming-simulator-25'],
                'category' => $categories['logistics-automation'],
                'game_phase' => 'Early Game (Opening / Year 1)',
                'missing_resource' => 'Cash Liquidity ($) & Silage Capacity',
                'user_query' => 'I own 3 medium fields and very little working capital. Should I plant soybeans for direct sale or invest in fermented corn chaff silage in the bunker?',
                'hit_count' => 7,
            ],
            [
                'game' => $games['baldurs-gate-3'],
                'category' => $categories['builds-loadouts'],
                'game_phase' => 'Mid Game (Industrialization / Act 2)',
                'missing_resource' => 'Spell Slots & Boss Crowd Control',
                'user_query' => 'I need to defeat the Apostle of Myrkul in Honour Mode without burning all my Spell Slots in Phase 1 against Ketheric Thorm and prevent him from consuming necromites.',
                'hit_count' => 11,
            ],
        ];

        foreach ($seedScenarios as $scenario) {
            $promptHash = $hasher->makeHash(
                (int) $scenario['game']->id,
                (int) $scenario['category']->id,
                $scenario['game_phase'],
                $scenario['missing_resource'],
                $scenario['user_query']
            );

            $history = PromptHistory::updateOrCreate(
                ['prompt_hash' => $promptHash],
                [
                    'uuid' => (string) Str::uuid(),
                    'game_id' => $scenario['game']->id,
                    'category_id' => $scenario['category']->id,
                    'game_phase' => $scenario['game_phase'],
                    'missing_resource' => $scenario['missing_resource'],
                    'user_query' => $scenario['user_query'],
                    'status' => 'queued',
                    'ai_model' => (string) config('services.gemini.model', 'gemini-3.5-flash-lite'),
                    'hit_count' => $scenario['hit_count'],
                ]
            );

            GenerateTacticalAdviceJob::dispatchSync($history->id);
        }
    }
}
