<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AnalyzeTacticRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'game_id' => ['required', 'integer', 'exists:games,id'],
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'game_phase' => ['required', 'string', 'max:80'],
            'missing_resource' => ['required', 'string', 'max:120'],
            'user_query' => ['required', 'string', 'min:8', 'max:1200'],
            'dispatch_mode' => ['nullable', 'string', 'in:async,sync'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'game_id.required' => 'Please select a target game from the tactical grid.',
            'game_id.exists' => 'The selected game is invalid.',
            'category_id.required' => 'Please select a tactical analysis category.',
            'game_phase.required' => 'Please specify the current match phase.',
            'missing_resource.required' => 'Please specify the missing resource or primary bottleneck.',
            'user_query.required' => 'Please describe your current game state for AI analysis.',
            'user_query.min' => 'Please provide at least 8 characters in the situation report.',
        ];
    }
}
