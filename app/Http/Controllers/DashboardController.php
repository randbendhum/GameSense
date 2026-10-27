<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Game;
use App\Models\PromptHistory;
use App\Models\TacticLog;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    /**
     * Renderiza o painel Neo-Brutalista principal com telemetria inicial do MySQL.
     */
    public function index(): View
    {
        $games = Game::query()
            ->where('is_active', true)
            ->orderBy('id')
            ->get();

        $categories = Category::query()
            ->orderBy('id')
            ->get();

        $recentLogs = TacticLog::query()
            ->with(['game', 'category', 'promptHistory'])
            ->latest('id')
            ->limit(8)
            ->get();

        $featuredLog = $recentLogs->first();

        $totalQueries = (int) PromptHistory::query()->count();
        $totalHits = (int) PromptHistory::query()->sum('hit_count');
        $cacheSavedCalls = max(0, $totalHits - $totalQueries);
        $cacheHitRatio = $totalHits > 0
            ? round(($cacheSavedCalls / $totalHits) * 100, 1)
            : 0.0;

        $telemetry = [
            'php_version' => PHP_VERSION,
            'laravel_version' => app()->version(),
            'ai_model' => (string) config('services.gemini.model', 'gemini-3.5-flash-lite'),
            'api_configured' => trim((string) config('services.gemini.api_key', '')) !== '',
            'vm_profile' => (string) config('services.gamesense.vm_profile', 'OCI E2.1.Micro (1GB RAM)'),
            'total_queries' => $totalQueries,
            'total_hits' => $totalHits,
            'cache_saved_calls' => $cacheSavedCalls,
            'cache_hit_ratio' => $cacheHitRatio,
            'memory_usage_mb' => round(memory_get_usage(true) / 1048576, 2),
        ];

        return view('dashboard', [
            'games' => $games,
            'categories' => $categories,
            'recentLogs' => $recentLogs,
            'featuredPayload' => $featuredLog?->toTerminalPayload(fromCache: true, responseTimeMs: 2),
            'telemetry' => $telemetry,
        ]);
    }
}

