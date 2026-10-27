<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Configuração da API Google Gemini (GameSense AI)
    |--------------------------------------------------------------------------
    | Modelo padrão: gemini-3.5-flash-lite
    | Para ativar chamadas reais em produção, basta preencher GEMINI_API_KEY
    | no seu arquivo .env (ou diretamente abaixo em 'api_key').
    | Enquanto 'api_key' estiver vazio, o AiTacticalService utiliza o motor
    | de Fallback Tático Determinístico automaticamente.
    */
    'gemini' => [
        'api_key' => env('GEMINI_API_KEY', ''), // <-- Configure sua API Key no .env ou aqui
        'model' => env('GEMINI_MODEL', 'gemini-3.5-flash-lite'),
        'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta/models'),
        'timeout' => (int) env('GEMINI_TIMEOUT_SECONDS', 20),
        'temperature' => (float) env('GEMINI_TEMPERATURE', 0.35),
        'max_output_tokens' => (int) env('GEMINI_MAX_OUTPUT_TOKENS', 900),
    ],

    'gamesense' => [
        // 'async' despacha para a fila do Laravel (Database Queue); 'sync' executa na mesma requisição
        'dispatch_mode' => env('GAMESENSE_DISPATCH_MODE', 'async'),
        'vm_profile' => 'OCI VM.Standard.E2.1.Micro (1/8 OCPU // 1GB RAM)',
    ],
];

