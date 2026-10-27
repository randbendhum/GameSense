<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Category;
use App\Models\Game;
use App\Models\PromptHistory;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class AiTacticalService
{
    /**
     * =========================================================================
     * GOOGLE GEMINI API INTEGRATION (gemini-3.5-flash-lite)
     * =========================================================================
     * Dispatches an HTTP POST request to the Gemini API (with automatic model
     * fallback if a preview model identifier is unavailable on the endpoint)
     * and falls back to the deterministic tactical engine if offline.
     */
    public function generateTacticalAnalysis(PromptHistory $promptHistory): array
    {
        $promptHistory->loadMissing(['game', 'category']);

        /** @var Game $game */
        $game = $promptHistory->game;
        /** @var Category $category */
        $category = $promptHistory->category;

        $apiKey = trim((string) config('services.gemini.api_key', ''));
        $configuredModel = (string) config('services.gemini.model', 'gemini-3.5-flash-lite');
        $baseUrl = rtrim((string) config('services.gemini.base_url', 'https://generativelanguage.googleapis.com/v1beta/models'), '/');
        $timeout = (int) config('services.gemini.timeout', 20);

        if ($apiKey === '') {
            return $this->buildDeterministicFallback($game, $category, $promptHistory, "{$configuredModel} [LOCAL-ENGINE]");
        }

        $systemPrompt = $this->buildSystemInstruction($game, $category);
        $userPrompt = $this->buildUserPrompt($game, $category, $promptHistory);

        // Try the configured model first, with fallback to gemini-2.0-flash-lite / gemini-1.5-flash if needed
        $candidateModels = array_values(array_unique([
            $configuredModel,
            'gemini-2.0-flash-lite',
            'gemini-1.5-flash',
        ]));

        foreach ($candidateModels as $model) {
            $endpoint = "{$baseUrl}/{$model}:generateContent?key={$apiKey}";

            try {
                $response = Http::timeout($timeout)
                    ->retry(1, 300)
                    ->acceptJson()
                    ->asJson()
                    ->post($endpoint, [
                        'systemInstruction' => [
                            'parts' => [
                                ['text' => $systemPrompt],
                            ],
                        ],
                        'contents' => [
                            [
                                'role' => 'user',
                                'parts' => [
                                    ['text' => $userPrompt],
                                ],
                            ],
                        ],
                        'generationConfig' => [
                            'temperature' => (float) config('services.gemini.temperature', 0.35),
                            'maxOutputTokens' => (int) config('services.gemini.max_output_tokens', 900),
                            'responseMimeType' => 'application/json',
                        ],
                    ]);

                if ($response->successful()) {
                    $rawText = (string) data_get($response->json(), 'candidates.0.content.parts.0.text', '');
                    $decoded = json_decode($rawText, true);

                    if (is_array($decoded) && isset($decoded['tactical_title'], $decoded['action_steps'])) {
                        return $this->normalizeAiResponse($decoded, $game, $category, $promptHistory, $configuredModel);
                    }
                }
            } catch (Throwable $exception) {
                Log::warning('GameSense AI: Gemini API call attempt failed.', [
                    'model' => $model,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return $this->buildDeterministicFallback($game, $category, $promptHistory, $configuredModel);
    }

    private function buildSystemInstruction(Game $game, Category $category): string
    {
        return implode("\n", [
            'You are GAMESENSE AI, a hardcore military-grade tactical analysis computer for strategy, RPG, and simulation gamers.',
            "Target Game: {$game->title} ({$game->genre} // Engine: {$game->engine_name}).",
            "Game Mechanical Context: {$game->system_prompt_context}",
            "Tactical Category: [{$category->code_tag}] {$category->name} — {$category->prompt_directive}",
            'Respond STRICTLY in valid English JSON with the following schema:',
            '{',
            '  "tactical_title": "SHORT UPPERCASE MILITARY TERMINAL DIRECTIVE TITLE",',
            '  "threat_level": "LOW" | "MEDIUM" | "CRITICAL",',
            '  "action_steps": ["Step 1 direct and actionable", "Step 2 with exact mechanics/numbers", "Step 3 risk mitigation", "Step 4 consolidation"],',
            '  "loadout_metrics": [',
            '    {"label": "METRIC NAME 1", "value": "SHORT VALUE"},',
            '    {"label": "METRIC NAME 2", "value": "SHORT VALUE"},',
            '    {"label": "METRIC NAME 3", "value": "SHORT VALUE"},',
            '    {"label": "METRIC NAME 4", "value": "SHORT VALUE"}',
            '  ],',
            '  "expected_outcome": "One-sentence summary of the net tactical gain after executing this protocol"',
            '}',
        ]);
    }

    private function buildUserPrompt(Game $game, Category $category, PromptHistory $promptHistory): string
    {
        return implode("\n", [
            "GAME: {$game->title}",
            "CATEGORY: {$category->name} ({$category->code_tag})",
            "CURRENT GAME PHASE: {$promptHistory->game_phase}",
            "CRITICAL MISSING RESOURCE / BOTTLENECK: {$promptHistory->missing_resource}",
            "PLAYER SITUATION REPORT: {$promptHistory->user_query}",
            'Generate the immediate tactical protocol in JSON.',
        ]);
    }

    /**
     * @param array<string, mixed> $decoded
     * @return array<string, mixed>
     */
    private function normalizeAiResponse(
        array $decoded,
        Game $game,
        Category $category,
        PromptHistory $promptHistory,
        string $modelUsed
    ): array {
        $title = mb_strtoupper((string) ($decoded['tactical_title'] ?? "PROTOCOL {$category->code_tag} // {$game->short_code}"), 'UTF-8');
        $threat = in_array($decoded['threat_level'] ?? 'MEDIUM', ['LOW', 'MEDIUM', 'CRITICAL'], true)
            ? (string) $decoded['threat_level']
            : 'MEDIUM';

        $steps = array_values(array_filter(array_map('strval', (array) ($decoded['action_steps'] ?? []))));
        if ($steps === []) {
            $steps = ['Reallocate priority resources immediately to resolve the ' . $promptHistory->missing_resource . ' bottleneck.'];
        }

        $metrics = is_array($decoded['loadout_metrics'] ?? null) ? $decoded['loadout_metrics'] : [];
        $outcome = (string) ($decoded['expected_outcome'] ?? 'Operational cycle stabilized and tactical margin restored.');

        return [
            'ai_model' => $modelUsed,
            'tactical_title' => $title,
            'threat_level' => $threat,
            'action_steps' => $steps,
            'loadout_metrics' => $metrics,
            'expected_outcome' => $outcome,
            'raw_markdown_response' => $this->compileTerminalMarkdown(
                $game,
                $category,
                $promptHistory,
                $title,
                $threat,
                $steps,
                $metrics,
                $outcome,
                $modelUsed
            ),
        ];
    }

    /**
     * Deterministic Tactical Engine in English.
     *
     * @return array<string, mixed>
     */
    private function buildDeterministicFallback(
        Game $game,
        Category $category,
        PromptHistory $promptHistory,
        string $modelLabel
    ): array {
        $resource = $promptHistory->missing_resource;
        $phase = $promptHistory->game_phase;
        $slug = $game->slug;

        $presets = [
            'victoria-3' => [
                'title' => 'INDUSTRIAL SHOCK DIRECTIVE // DE-BOTTLENECKING ' . mb_strtoupper($resource, 'UTF-8'),
                'threat' => 'CRITICAL',
                'steps' => [
                    "Immediately pause secondary Construction Sector expansions during [{$phase}] to halt the bleeding deficit on [{$resource}].",
                    "Enact the 'Encourage Resource Industry' decree in your highest-throughput state and establish a tariff-free import route for [{$resource}].",
                    "Switch Production Methods (PMs) incrementally (1 state at a time) to prevent market price shocks exceeding +45% above base price.",
                    "Levy Consumption Taxes on Luxury Goods to fund temporary subsidies on railway infrastructure and extractive mines.",
                ],
                'metrics' => [
                    ['label' => 'MARKET EQUILIBRIUM', 'value' => '-38% BASE PRICE'],
                    ['label' => 'STATE THROUGHPUT', 'value' => '+25% OUTPUT'],
                    ['label' => 'GOLD RESERVE', 'value' => 'STABLE IN 16 WKS'],
                    ['label' => 'RADICALS PREVENTED', 'value' => '-14.2K POPS'],
                ],
                'outcome' => "Normalizes {$resource} market cost below +10% base price and restores GDP growth within 12–18 weeks.",
            ],
            'hollow-knight' => [
                'title' => 'HALLOWNEST COMBAT PROTOCOL // OPTIMIZING ' . mb_strtoupper($resource, 'UTF-8'),
                'threat' => 'CRITICAL',
                'steps' => [
                    "Reconfigure your charm loadout for [{$phase}]: equip Quick Slash (3 notches) + Shaman Stone (3 notches) + Spell Twister (2 notches).",
                    "Replace risky mid-fight Focus heals with Descending Dark (0.4s of invulnerability frames) to preserve [{$resource}] while dealing AoE damage.",
                    "Execute aerial pogo-strikes after the boss's 2nd dash to regenerate [{$resource}] while staying clear of ground hitboxes.",
                    "Restrict Focus healing strictly to boss stagger transitions (triggered every 6–8 consecutive Pure Nail hits).",
                ],
                'metrics' => [
                    ['label' => 'BURST DPS CYCLE', 'value' => '185–240 DMG'],
                    ['label' => 'I-FRAME WINDOW', 'value' => '400ms (D-DARK)'],
                    ['label' => 'SOUL EFFICIENCY', 'value' => '24 SOUL / CAST'],
                    ['label' => 'SURVIVAL RATE', 'value' => '+68% IN PANTHEON'],
                ],
                'outcome' => "Full arena control during {$phase} with a constant {$resource} reserve for invulnerability spells.",
            ],
            'farming-simulator-25' => [
                'title' => 'AGRO-LOGISTICS OPTIMIZATION // SCALING ' . mb_strtoupper($resource, 'UTF-8'),
                'threat' => 'MEDIUM',
                'steps' => [
                    "Suspend heavy machinery purchases during [{$phase}] and use short-term leasing exclusively for wide-header harvesters.",
                    "Divert raw crops into a vertical value chain (Fermented Bunker Silage / Canola Oil / Fabric) to multiply [{$resource}] returns.",
                    "Apply agricultural lime + double fertilization + post-seeding roller pass to lock in a +97% to +100% yield bonus per hectare.",
                    "Hold inventory in silos and sell exclusively during peak seasonal calendar months (December/January) rather than harvesting direct-to-sell.",
                ],
                'metrics' => [
                    ['label' => 'NET MARGIN / HA', 'value' => '+44% PROFIT'],
                    ['label' => 'SOIL YIELD BONUS', 'value' => '98% EFFICIENCY'],
                    ['label' => 'CYCLE PAYBACK', 'value' => '2 SEASONS'],
                    ['label' => 'CAPEX SAVED', 'value' => '-62% VIA LEASE'],
                ],
                'outcome' => "Generates a strong {$resource} surplus by the next seasonal price peak with full operational payback.",
            ],
            'baldurs-gate-3' => [
                'title' => 'D&D 5E TACTICAL MATRIX (HONOUR MODE) // MANAGING ' . mb_strtoupper($resource, 'UTF-8'),
                'threat' => 'CRITICAL',
                'steps' => [
                    "Initiate combat in [{$phase}] from stealth (Turn-Based Mode enabled) to trigger the 'Surprised' condition in Round 1 without burning [{$resource}].",
                    "Cluster enemies with Minor Illusion or Void Bulbs and apply the 'Wet' condition (Create Water / thrown water bottle) to double Lightning and Cold damage.",
                    "Focus Action Surge + Speed Potion burst on the primary boss while locking down chokepoints with Hunger of Hadar and Bone Chill.",
                    "Keep one party member anchored at range with Misty Step / Sanctuary prepared to prevent a party wipe (TPK) on Legendary Resistance rolls.",
                ],
                'metrics' => [
                    ['label' => 'ACTION ECONOMY', 'value' => '+2 ACTIONS RD 1'],
                    ['label' => 'DMG MULTIPLIER', 'value' => '2.0x (WET STATUS)'],
                    ['label' => 'HIT PROBABILITY', 'value' => '88%–95% ADV'],
                    ['label' => 'HONOUR WIPE RISK', 'value' => '< 4% PROBABILITY'],
                ],
                'outcome' => "Neutralizes priority boss targets before Round 2 while preserving {$resource} for the remainder of the act.",
            ],
        ];

        $selected = $presets[$slug] ?? $presets['victoria-3'];

        return [
            'ai_model' => $modelLabel,
            'tactical_title' => $selected['title'],
            'threat_level' => $selected['threat'],
            'action_steps' => $selected['steps'],
            'loadout_metrics' => $selected['metrics'],
            'expected_outcome' => $selected['outcome'],
            'raw_markdown_response' => $this->compileTerminalMarkdown(
                $game,
                $category,
                $promptHistory,
                $selected['title'],
                $selected['threat'],
                $selected['steps'],
                $selected['metrics'],
                $selected['outcome'],
                $modelLabel
            ),
        ];
    }

    /**
     * @param array<int, string> $steps
     * @param array<int, array{label?: string, value?: string}> $metrics
     */
    private function compileTerminalMarkdown(
        Game $game,
        Category $category,
        PromptHistory $promptHistory,
        string $title,
        string $threat,
        array $steps,
        array $metrics,
        string $outcome,
        string $modelUsed
    ): string {
        $lines = [
            '====================================================================',
            'GAMESENSE AI // FIELD TACTICAL REPORT',
            '====================================================================',
            "GAME       : {$game->title} [{$game->short_code}]",
            "CATEGORY   : [{$category->code_tag}] {$category->name}",
            "PHASE      : {$promptHistory->game_phase}",
            "BOTTLENECK : {$promptHistory->missing_resource}",
            "AI ENGINE  : {$modelUsed}",
            "THREAT LVL : {$threat}",
            "SHA256 HASH: {$promptHistory->prompt_hash}",
            '--------------------------------------------------------------------',
            "DIRECTIVE  : {$title}",
            '--------------------------------------------------------------------',
            '',
            '[IMMEDIATE EXECUTION STEPS]',
        ];

        foreach ($steps as $idx => $step) {
            $num = str_pad((string) ($idx + 1), 2, '0', STR_PAD_LEFT);
            $lines[] = "  {$num}. {$step}";
        }

        $lines[] = '';
        $lines[] = '[TELEMETRY & BUILD PARAMETERS]';
        foreach ($metrics as $metric) {
            $label = str_pad((string) ($metric['label'] ?? 'METRIC'), 22, ' ', STR_PAD_RIGHT);
            $value = (string) ($metric['value'] ?? 'N/A');
            $lines[] = "  * {$label}: {$value}";
        }

        $lines[] = '';
        $lines[] = '[PROJECTED OUTCOME]';
        $lines[] = "  > {$outcome}";
        $lines[] = '====================================================================';

        return implode("\n", $lines);
    }
}
