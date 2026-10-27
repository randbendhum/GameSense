<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    protected $table = 'categories';

    protected $fillable = [
        'slug',
        'name',
        'code_tag',
        'description',
        'prompt_directive',
    ];

    /**
     * @return HasMany<PromptHistory, $this>
     */
    public function promptsHistory(): HasMany
    {
        return $this->hasMany(PromptHistory::class, 'category_id');
    }

    /**
     * @return HasMany<TacticLog, $this>
     */
    public function tacticsLogs(): HasMany
    {
        return $this->hasMany(TacticLog::class, 'category_id');
    }
}

