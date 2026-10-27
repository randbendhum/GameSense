<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabela relacional `tactics_logs` — Respostas táticas estruturadas geradas pela IA.
     */
    public function up(): void
    {
        Schema::create('tactics_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('prompt_history_id')->unique()->constrained('prompts_history')->cascadeOnDelete();
            $table->foreignId('game_id')->constrained('games')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->string('tactical_title', 180);
            $table->enum('threat_level', ['LOW', 'MEDIUM', 'CRITICAL'])->default('MEDIUM');
            $table->json('action_steps'); // Passos táticos numerados
            $table->json('loadout_metrics'); // Métricas/parâmetros recomendados (ex.: ROI, DPS, Eficiência)
            $table->string('expected_outcome', 255);
            $table->longText('raw_markdown_response'); // Texto completo formatado para terminal / exportação
            $table->boolean('is_pinned')->default(false)->index();
            $table->timestamps();

            $table->index(['game_id', 'category_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tactics_logs');
    }
};

