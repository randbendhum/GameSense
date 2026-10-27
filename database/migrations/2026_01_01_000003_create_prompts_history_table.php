<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabela relacional `prompts_history` — Histórico de pedidos e chave primária de desduplicação (SHA-256).
     */
    public function up(): void
    {
        Schema::create('prompts_history', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            // Hash SHA-256 da pergunta normalizada para entrega instantânea em milissegundos
            $table->char('prompt_hash', 64)->unique();
            $table->foreignId('game_id')->constrained('games')->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('categories')->cascadeOnDelete();
            $table->string('game_phase', 80); // Early Game, Mid Game, Late Game / Endgame
            $table->string('missing_resource', 120); // Ex.: Carvão / Ferro, Soul / Geo, Liquidez
            $table->text('user_query'); // Situação tática detalhada pelo jogador
            $table->enum('status', ['queued', 'processing', 'completed', 'failed'])->default('queued')->index();
            $table->string('ai_model', 64)->default('gemini-3.5-flash-lite');
            $table->unsignedInteger('execution_time_ms')->default(0);
            $table->unsignedInteger('hit_count')->default(1); // Quantas chamadas de API foram economizadas pelo cache
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['game_id', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prompts_history');
    }
};

