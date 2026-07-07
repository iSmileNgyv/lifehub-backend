<?php

namespace App\AI\Providers;

use App\AI\AiProvider;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAiProvider implements AiProvider
{
    public function __construct(
        private ?string $key,
        private ?string $model,
        private string $baseUrl,
    ) {}

    public function generateFields(array $fields, string $prompt, string $instruction = ''): array
    {
        if (empty($this->key)) {
            throw new RuntimeException('AI konfiqurasiya olunmayıb: OPENAI_API_KEY .env-də yoxdur.');
        }
        if (empty($this->model)) {
            throw new RuntimeException('AI konfiqurasiya olunmayıb: OPENAI_MODEL .env-də yoxdur.');
        }
        if (empty($fields)) {
            return [];
        }

        $lines = [];
        foreach ($fields as $f) {
            $lines[] = '- '.$f['key'].': '.($f['description'] ?: $f['key']);
        }

        $system = 'You are a language-learning assistant that fills flashcard fields. '
            .'Return ONLY a JSON object whose keys are EXACTLY the requested field keys and whose values are strings. '
            .'Follow each field description precisely. If a value is unknown, use an empty string. Do not add extra keys. '
            .'Values may contain simple inline HTML (<b>, <i>, <u>, <span style="color:..."></span>) when the instruction asks for formatting.';

        $instructionBlock = trim($instruction) !== '' ? "General instruction:\n".$instruction."\n\n" : '';
        $user = $instructionBlock."Word / prompt:\n".$prompt."\n\nFill these fields (key: description):\n".implode("\n", $lines);

        $res = Http::withToken($this->key)
            ->timeout(60)
            ->post($this->baseUrl.'/chat/completions', [
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => $system],
                    ['role' => 'user', 'content' => $user],
                ],
                'response_format' => ['type' => 'json_object'],
                'temperature' => 0.3,
            ]);

        if (! $res->successful()) {
            throw new RuntimeException('AI xətası ('.$res->status().'): '.$res->json('error.message', $res->body()));
        }

        $data = json_decode((string) $res->json('choices.0.message.content'), true);
        if (! is_array($data)) {
            throw new RuntimeException('AI cavabı oxunmadı.');
        }

        // Yalnız tələb olunan açarları saxla
        $out = [];
        foreach ($fields as $f) {
            $v = $data[$f['key']] ?? null;
            if ($v !== null && $v !== '') {
                $out[$f['key']] = (string) $v;
            }
        }

        return $out;
    }
}
