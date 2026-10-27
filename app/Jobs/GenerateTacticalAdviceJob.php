<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\PromptHistory;
use App\Models\TacticLog;
use App\Services\AiTacticalService;
use App\Services\TacticalPromptHasher;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class GenerateTacticalAdviceJob implements ShouldQueue
{
    use Queueable;

    /**
     * Número máximo de tentativas na fila (otimizado para não prender a CPU E2.1.Micro).
     */
    public int $tries = 2;

    /**
     * Timeout máximo por job em segundos.
     */
    public int $timeout = 35;

    public function __construct(
        public readonly int $promptHistoryId
    ) {}

    public function handle(
        AiTacticalService $aiService,
        TacticalPromptHasher $hasher
    ): void {
        $startTime = hrtime(true);

        /** @var PromptHistory|null $promptHistory */
        $promptHistory = PromptHistory::with(['game', 'category', 'tacticLog'])->find($this->promptHistoryId);

        if ($promptHistory === null) {
            return;
        }

        // Se já foi concluído por outra requisição concorrente, apenas garante aquecimento do cache
        if ($promptHistory->status === 'completed' && $promptHistory->tacticLog !== null) {
            $this->warmCache($promptHistory, $promptHistory->tacticLog, $hasher);

            return;
        }

        $promptHistory->update([
            'status' => 'processing',
        ]);

        try {
            $analysis = $aiService->generateTacticalAnalysis($promptHistory);
            $elapsedMs = (int) max(1, round((hrtime(true) - $startTime) / 1_000_000));

            /** @var TacticLog $tacticLog */
            $tacticLog = DB::transaction(function () use ($promptHistory, $analysis, $elapsedMs): TacticLog {
                $log = TacticLog::updateOrCreate(
                    ['prompt_history_id' => $promptHistory->id],
                    [
                        'game_id' => $promptHistory->game_id,
                        'category_id' => $promptHistory->category_id,
                        'tactical_title' => $analysis['tactical_title'],
                        'threat_level' => $analysis['threat_level'],
                        'action_steps' => $analysis['action_steps'],
                        'loadout_metrics' => $analysis['loadout_metrics'],
                        'expected_outcome' => $analysis['expected_outcome'],
                        'raw_markdown_response' => $analysis['raw_markdown_response'],
                    ]
                );

                $promptHistory->update([
                    'status' => 'completed',
                    'ai_model' => $analysis['ai_model'],
                    'execution_time_ms' => $elapsedMs,
                    'error_message' => null,
                ]);

                return $log;
            });

            $tacticLog->load(['game', 'category', 'promptHistory']);
            $this->warmCache($promptHistory, $tacticLog, $hasher);
        } catch (Throwable $exception) {
            $promptHistory->update([
                'status' => 'failed',
                'error_message' => mb_substr($exception->getMessage(), 0, 500),
            ]);

            throw $exception;
        }
    }

    private function warmCache(
        PromptHistory $promptHistory,
        TacticLog $tacticLog,
        TacticalPromptHasher $hasher
    ): void {
        $ttlSeconds = (int) config('cache.ttl_seconds', 604800);
        $cacheKey = $hasher->cacheKey($promptHistory->prompt_hash);

        Cache::put($cacheKey, $tacticLog->toTerminalPayload(fromCache: true, responseTimeMs: 2), $ttlSeconds);
    }
}

