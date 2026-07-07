<?php

namespace App\AI;

use App\AI\Providers\OpenAiProvider;
use InvalidArgumentException;

/**
 * .env AI_PROVIDER-ə görə uyğun AI provider qaytarır.
 * Gələcəkdə: deepseek, claude — burada case əlavə olunur.
 */
class AiFactory
{
    public static function make(): AiProvider
    {
        $provider = config('ai.provider', 'openai');

        return match ($provider) {
            'openai' => new OpenAiProvider(
                config('ai.openai.key'),
                config('ai.openai.model'),
                rtrim((string) config('ai.openai.base_url'), '/'),
            ),
            // 'deepseek' => new Providers\DeepSeekProvider(...),   // sonra
            // 'claude' => new Providers\ClaudeProvider(...),       // sonra
            default => throw new InvalidArgumentException("Dəstəklənməyən AI provider: {$provider}"),
        };
    }
}
