<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Game extends Model
{
    protected $table = 'games';

    protected $fillable = [
        'slug',
        'title',
        'short_code',
        'genre',
        'engine_name',
        'accent_color',
        'default_resources',
        'system_prompt_context',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'default_resources' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<PromptHistory, $this>
     */
    public function promptsHistory(): HasMany
    {
        return $this->hasMany(PromptHistory::class, 'game_id');
    }

    /**
     * @return HasMany<TacticLog, $this>
     */
    public function tacticsLogs(): HasMany
    {
        return $this->hasMany(TacticLog::class, 'game_id');
    }
}

