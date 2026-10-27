<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PromptHistory extends Model
{
    protected $table = 'prompts_history';

    protected $fillable = [
        'uuid',
        'prompt_hash',
        'game_id',
        'category_id',
        'game_phase',
        'missing_resource',
        'user_query',
        'status',
        'ai_model',
        'execution_time_ms',
        'hit_count',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'execution_time_ms' => 'integer',
            'hit_count' => 'integer',
        ];
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
     * @return HasOne<TacticLog, $this>
     */
    public function tacticLog(): HasOne
    {
        return $this->hasOne(TacticLog::class, 'prompt_history_id');
    }
}

