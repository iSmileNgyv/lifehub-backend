<?php

return [
    // Aktiv AI provider: openai (gələcəkdə: deepseek, claude...)
    'provider' => env('AI_PROVIDER', 'openai'),

    'openai' => [
        'key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL'),          // .env-dən (hardcode yox)
        'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
        // Yalnız reasoning modelləri üçün (gpt-5.x / pro): minimal|low|medium|high.
        // Boş buraxılsa göndərilmir — adi modellər (gpt-4o) pozulmur.
        'reasoning_effort' => env('OPENAI_REASONING_EFFORT'),
    ],
];
