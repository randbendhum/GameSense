<?php

declare(strict_types=1);

namespace App\Services;

class TacticalPromptHasher
{
    /**
     * Normaliza o estado tático enviado pelo jogador e gera uma hash SHA-256 determinística.
     * Garante que perguntas equivalentes reutilizem o Cache em milissegundos sem custo de API.
     */
    public function makeHash(
        int $gameId,
        int $categoryId,
        string $gamePhase,
        string $missingResource,
        string $userQuery
    ): string {
        $normalized = implode('|', [
            (string) $gameId,
            (string) $categoryId,
            $this->normalizeSegment($gamePhase),
            $this->normalizeSegment($missingResource),
            $this->normalizeSegment($userQuery),
        ]);

        return hash('sha256', $normalized);
    }

    /**
     * Chave usada na tabela `cache` do Laravel.
     */
    public function cacheKey(string $promptHash): string
    {
        return "tactic_hash:{$promptHash}";
    }

    private function normalizeSegment(string $input): string
    {
        $clean = mb_strtolower(trim($input), 'UTF-8');
        $clean = preg_replace('/\s+/u', ' ', $clean) ?? $clean;

        return $clean;
    }
}

