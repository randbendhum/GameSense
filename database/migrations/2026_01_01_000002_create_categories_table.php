<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabela relacional `categories` — Categorias de análise tática.
     */
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 64)->unique();
            $table->string('name', 100);
            $table->string('code_tag', 16)->unique(); // Ex.: ECO-01, BLD-02, CMB-03, LOG-04
            $table->string('description', 255);
            $table->text('prompt_directive'); // Diretriz de resposta específica da categoria
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};

