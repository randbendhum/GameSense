<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TacticLog extends Model
{
    protected $table = 'tactics_logs';

    protected $fillable = [
        'prompt_history_id',
        'game_id',
        'category_id',
        'tactical_title',
        'threat_level',
        'action_steps',
        'loadout_metrics',
        'expected_outcome',
        'raw_markdown_response',
        'is_pinned',
    ];

    protected function casts(): array
    {
        return [
            'action_steps' => 'array',
            'loadout_metrics' => 'array',
            'is_pinned' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<PromptHistory, $this>
     */
    public function promptHistory(): BelongsTo
    {
        return $this->belongsTo(PromptHistory::class, 'prompt_history_id');
    }

    /**
     * @return BelongsTo<Game, $this>
     */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class, 'game_id');
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    /**
     * Serializa o log tático em formato estruturado para API e Cache.
     *
     * @return array<string, mixed>
     */
    public function toTerminalPayload(bool $fromCache = false, int $responseTimeMs = 2): array
    {
        $history = $this->promptHistory;

        return [
            'id' => $this->id,
            'uuid' => $history?->uuid,
            'prompt_hash' => $history?->prompt_hash,
            'cached' => $fromCache,
            'response_time_ms' => $fromCache ? $responseTimeMs : ($history?->execution_time_ms ?? $responseTimeMs),
            'hit_count' => $history?->hit_count ?? 1,
            'ai_model' => $history?->ai_model ?? config('services.gemini.model', 'gemini-3.5-flash-lite'),
            'game' => [
                'id' => $this->game?->id,
                'slug' => $this->game?->slug,
                'title' => $this->game?->title,
                'short_code' => $this->game?->short_code,
                'genre' => $this->game?->genre,
                'accent_color' => $this->game?->accent_color,
            ],
            'category' => [
                'id' => $this->category?->id,
                'slug' => $this->category?->slug,
                'name' => $this->category?->name,
                'code_tag' => $this->category?->code_tag,
            ],
            'telemetry' => [
                'game_phase' => $history?->game_phase,
                'missing_resource' => $history?->missing_resource,
                'user_query' => $history?->user_query,
            ],
            'tactical_title' => $this->tactical_title,
            'threat_level' => $this->threat_level,
            'action_steps' => $this->action_steps ?? [],
            'loadout_metrics' => $this->loadout_metrics ?? [],
            'expected_outcome' => $this->expected_outcome,
            'raw_markdown_response' => $this->raw_markdown_response,
            'created_at_formatted' => $this->created_at?->format('Y-m-d H:i:s') ?? now()->format('Y-m-d H:i:s'),
        ];
    }
}

