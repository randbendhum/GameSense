<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabela relacional `games` — Jogos suportados pelo painel tático.
     */
    public function up(): void
    {
        Schema::create('games', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 64)->unique();
            $table->string('title', 120);
            $table->string('short_code', 12); // Ex.: VIC-03, HK-01, FS-25, BG-03
            $table->string('genre', 60); // Ex.: Grand Strategy, Metroidvania, Simulation, CRPG
            $table->string('engine_name', 80); // Ex.: Clausewitz Engine, Unity, GIANTS Engine 10, Divinity 4.0
            $table->string('accent_color', 16)->default('#FFDE59'); // Realce ácido Neo-Brutalista
            $table->json('default_resources')->nullable(); // Sugestões rápidas de recursos do jogo
            $table->text('system_prompt_context'); // Contexto tático injetado no prompt da IA
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('games');
    }
};

