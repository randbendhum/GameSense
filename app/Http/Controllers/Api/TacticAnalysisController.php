<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AnalyzeTacticRequest;
use App\Jobs\GenerateTacticalAdviceJob;
use App\Models\PromptHistory;
use App\Models\TacticLog;
use App\Services\TacticalPromptHasher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class TacticAnalysisController extends Controller
{
    /**
     * Handles the player's tactical analysis request:
     * 1. Checks SHA-256 Hash for an instant Cache Hit (delivered in milliseconds).
     * 2. On Cache Miss, dispatches GenerateTacticalAdviceJob to the Laravel Database Queue.
     */
    public function analyze(
        AnalyzeTacticRequest $request,
        TacticalPromptHasher $hasher
    ): JsonResponse {
        $startTime = hrtime(true);
        $validated = $request->validated();

        $promptHash = $hasher->makeHash(
            (int) $validated['game_id'],
            (int) $validated['category_id'],
            (string) $validated['game_phase'],
            (string) $validated['missing_resource'],
            (string) $validated['user_query']
        );

        $cacheKey = $hasher->cacheKey($promptHash);

        // 1. Check MySQL `prompt_hash` unique index for an existing completed analysis
        /** @var PromptHistory|null $existingHistory */
        $existingHistory = PromptHistory::with(['tacticLog.game', 'tacticLog.category', 'tacticLog.promptHistory'])
            ->where('prompt_hash', $promptHash)
            ->first();

        if ($existingHistory !== null && $existingHistory->status === 'completed' && $existingHistory->tacticLog !== null) {
            $existingHistory->increment('hit_count');
            $existingHistory->refresh();

            $elapsedMs = (int) max(1, round((hrtime(true) - $startTime) / 1_000_000));
            $payload = $existingHistory->tacticLog->toTerminalPayload(fromCache: true, responseTimeMs: $elapsedMs);

            Cache::put($cacheKey, $payload, (int) config('cache.ttl_seconds', 604800));

            return response()->json([
                'status' => 'completed',
                'cached' => true,
                'response_time_ms' => $elapsedMs,
                'prompt_hash' => $promptHash,
                'data' => $payload,
            ], 200);
        }

        // 2. Cache Miss: Record in `prompts_history` and dispatch to the Queue
        $promptHistory = PromptHistory::updateOrCreate(
            ['prompt_hash' => $promptHash],
            [
                'uuid' => $existingHistory?->uuid ?? (string) Str::uuid(),
                'game_id' => (int) $validated['game_id'],
                'category_id' => (int) $validated['category_id'],
                'game_phase' => (string) $validated['game_phase'],
                'missing_resource' => (string) $validated['missing_resource'],
                'user_query' => (string) $validated['user_query'],
                'status' => 'queued',
                'ai_model' => (string) config('services.gemini.model', 'gemini-3.5-flash-lite'),
                'error_message' => null,
            ]
        );

        $dispatchMode = (string) ($validated['dispatch_mode'] ?? config('services.gamesense.dispatch_mode', 'async'));
        $queueDriver = (string) config('queue.default', 'database');

        if ($dispatchMode === 'sync' || $queueDriver === 'sync') {
            GenerateTacticalAdviceJob::dispatchSync($promptHistory->id);
            $promptHistory->load(['tacticLog.game', 'tacticLog.category', 'tacticLog.promptHistory']);

            $elapsedMs = (int) max(1, round((hrtime(true) - $startTime) / 1_000_000));
            $payload = $promptHistory->tacticLog?->toTerminalPayload(fromCache: false, responseTimeMs: $elapsedMs);

            return response()->json([
                'status' => 'completed',
                'cached' => false,
                'response_time_ms' => $elapsedMs,
                'prompt_hash' => $promptHash,
                'data' => $payload,
            ], 200);
        }

        // Asynchronous dispatch to the MySQL `jobs` queue table
        GenerateTacticalAdviceJob::dispatch($promptHistory->id);

        return response()->json([
            'status' => 'queued',
            'cached' => false,
            'uuid' => $promptHistory->uuid,
            'prompt_hash' => $promptHash,
            'poll_url' => route('api.tactics.status', ['uuid' => $promptHistory->uuid]),
            'message' => 'Request queued in Laravel Database Queue. Awaiting AI processing...',
        ], 202);
    }

    /**
     * Polls the Job status (`queued` -> `processing` -> `completed`).
     * Includes self-healing fallback if the background worker has not picked up the job after 2s.
     */
    public function status(string $uuid): JsonResponse
    {
        /** @var PromptHistory|null $promptHistory */
        $promptHistory = PromptHistory::with(['tacticLog.game', 'tacticLog.category', 'tacticLog.promptHistory'])
            ->where('uuid', $uuid)
            ->firstOrFail();

        if ($promptHistory->status === 'queued' && $promptHistory->updated_at !== null && $promptHistory->updated_at->diffInSeconds(now()) >= 2) {
            GenerateTacticalAdviceJob::dispatchSync($promptHistory->id);
            $promptHistory->refresh();
            $promptHistory->load(['tacticLog.game', 'tacticLog.category', 'tacticLog.promptHistory']);
        }

        if ($promptHistory->status === 'completed' && $promptHistory->tacticLog !== null) {
            return response()->json([
                'status' => 'completed',
                'cached' => false,
                'uuid' => $promptHistory->uuid,
                'prompt_hash' => $promptHistory->prompt_hash,
                'data' => $promptHistory->tacticLog->toTerminalPayload(
                    fromCache: false,
                    responseTimeMs: $promptHistory->execution_time_ms
                ),
            ], 200);
        }

        if ($promptHistory->status === 'failed') {
            return response()->json([
                'status' => 'failed',
                'uuid' => $promptHistory->uuid,
                'message' => $promptHistory->error_message ?? 'Failed to process tactical analysis.',
            ], 500);
        }

        return response()->json([
            'status' => $promptHistory->status,
            'uuid' => $promptHistory->uuid,
            'prompt_hash' => $promptHistory->prompt_hash,
        ], 200);
    }

    /**
     * Loads a cached tactical log entry from MySQL in milliseconds.
     */
    public function showLog(int $id): JsonResponse
    {
        $startTime = hrtime(true);

        /** @var TacticLog $log */
        $log = TacticLog::with(['game', 'category', 'promptHistory'])->findOrFail($id);
        $log->promptHistory?->increment('hit_count');
        $log->promptHistory?->refresh();

        $elapsedMs = (int) max(1, round((hrtime(true) - $startTime) / 1_000_000));

        return response()->json([
            'status' => 'completed',
            'cached' => true,
            'response_time_ms' => $elapsedMs,
            'data' => $log->toTerminalPayload(fromCache: true, responseTimeMs: $elapsedMs),
        ]);
    }

    /**
     * Lists recent tactical logs (filterable by `game_id`).
     */
    public function history(Request $request): JsonResponse
    {
        $query = TacticLog::with(['game', 'category', 'promptHistory'])->latest('id');

        if ($request->filled('game_id')) {
            $query->where('game_id', (int) $request->integer('game_id'));
        }

        $logs = $query->limit(10)->get()->map(
            fn (TacticLog $log): array => $log->toTerminalPayload(fromCache: true, responseTimeMs: 2)
        );

        return response()->json([
            'count' => $logs->count(),
            'items' => $logs,
        ]);
    }
}
