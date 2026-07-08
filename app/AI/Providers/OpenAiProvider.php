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
        private ?string $reasoningEffort = null,
    ) {}

    public function generateFields(array $fields, string $prompt, string $instruction = ''): array
    {
        $this->assertConfigured();
        if (empty($fields)) {
            return [];
        }

        $system = 'You fill flashcard fields. Return ONLY a JSON object; keys must be EXACTLY the requested field keys; values are strings. '
            .'Fill each field strictly per its label, description and the general instruction — apply no rules of your own. '
            .'Unknown → empty string. No extra keys. Use inline HTML only if a description/instruction asks.';

        $user = $this->instructionBlock($instruction)
            ."Word / prompt:\n".$prompt
            ."\n\nFill these fields (key: description):\n".$this->fieldLines($fields);

        $data = $this->call($system, $user);

        return $this->pick($data, $fields);
    }

    public function generateFieldsBatch(array $fields, array $prompts, string $instruction = ''): array
    {
        $this->assertConfigured();
        $prompts = array_values(array_unique(array_filter(array_map('trim', $prompts), fn ($w) => $w !== '')));
        if (empty($fields) || empty($prompts)) {
            return [];
        }

        $system = 'You fill flashcard fields for several words at once. '
            .'Return ONLY a JSON object whose keys are EXACTLY the given words; each value is an object whose keys are EXACTLY the requested field keys (string values). '
            .'Fill each field strictly per its label, description and the general instruction — apply no rules of your own. '
            .'Unknown → empty string. No extra keys. Use inline HTML only if a description/instruction asks.';

        $wordList = implode("\n", array_map(fn ($w) => '- '.$w, $prompts));
        $user = $this->instructionBlock($instruction)
            ."Words (fill each one):\n".$wordList
            ."\n\nFor every word fill these fields (key: description):\n".$this->fieldLines($fields);

        $data = $this->call($system, $user);

        $out = [];
        foreach ($prompts as $w) {
            $row = is_array($data[$w] ?? null) ? $data[$w] : [];
            $out[$w] = $this->pick($row, $fields);
        }

        return $out;
    }

    private function assertConfigured(): void
    {
        if (empty($this->key)) {
            throw new RuntimeException('AI konfiqurasiya olunmayıb: OPENAI_API_KEY .env-də yoxdur.');
        }
        if (empty($this->model)) {
            throw new RuntimeException('AI konfiqurasiya olunmayıb: OPENAI_MODEL .env-də yoxdur.');
        }
    }

    /** @param array<int, array{key: string, label?: string, description: ?string}> $fields */
    private function fieldLines(array $fields): string
    {
        $lines = [];
        foreach ($fields as $f) {
            $label = ! empty($f['label']) ? ' ['.$f['label'].']' : '';
            $lines[] = '- '.$f['key'].$label.': '.($f['description'] ?: $f['key']);
        }

        return implode("\n", $lines);
    }

    private function instructionBlock(string $instruction): string
    {
        return trim($instruction) !== '' ? "General instruction:\n".$instruction."\n\n" : '';
    }

    /**
     * Responses API çağırışı — həm klassik, həm reasoning modellərlə işləyir.
     *
     * @return array<string, mixed>
     */
    private function call(string $system, string $user): array
    {
        $payload = [
            'model' => $this->model,
            'input' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $user],
            ],
            'text' => ['format' => ['type' => 'json_object']],
        ];
        if (! empty($this->reasoningEffort)) {
            $payload['reasoning'] = ['effort' => $this->reasoningEffort];
        }

        $res = Http::withToken($this->key)
            ->timeout(180)
            ->post($this->baseUrl.'/responses', $payload);

        if (! $res->successful()) {
            throw new RuntimeException('AI xətası ('.$res->status().'): '.$res->json('error.message', $res->body()));
        }

        // Cavab mətnini çıxar: əvvəlcə convenience `output_text`, olmasa `output[].content[].output_text`.
        $content = (string) $res->json('output_text', '');
        if ($content === '') {
            foreach ($res->json('output', []) as $item) {
                if (($item['type'] ?? '') !== 'message') {
                    continue;
                }
                foreach ($item['content'] ?? [] as $c) {
                    if (($c['type'] ?? '') === 'output_text') {
                        $content .= $c['text'] ?? '';
                    }
                }
            }
        }

        $data = json_decode($content, true);
        if (! is_array($data)) {
            throw new RuntimeException('AI cavabı oxunmadı.');
        }

        return $data;
    }

    /**
     * Yalnız tələb olunan açarları saxla.
     *
     * @param  array<string, mixed>  $data
     * @param  array<int, array{key: string}>  $fields
     * @return array<string, string>
     */
    private function pick(array $data, array $fields): array
    {
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
