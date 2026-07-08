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
            $label = ! empty($f['label']) ? ' ['.$f['label'].']' : '';
            $lines[] = '- '.$f['key'].$label.': '.($f['description'] ?: $f['key']);
        }

        $system = 'You fill flashcard fields. '
            .'Return ONLY a JSON object whose keys are EXACTLY the requested field keys and whose values are strings. '
            .'Fill each field strictly according to its label, its description and the general instruction — these are the only sources of what the content should be. Do not apply any rules of your own beyond them. '
            .'If a value is unknown, use an empty string. Do not add keys that were not requested. '
            .'Values may contain simple inline HTML (<b>, <i>, <u>, <span style="color:..."></span>) when a description or the general instruction asks for such formatting.';

        $instructionBlock = trim($instruction) !== '' ? "General instruction:\n".$instruction."\n\n" : '';
        $user = $instructionBlock."Word / prompt:\n".$prompt."\n\nFill these fields (key: description):\n".implode("\n", $lines);

        // Responses API — həm klassik (gpt-4o/mini), həm yeni/reasoning (gpt-5.x, pro) modellərlə işləyir.
        $payload = [
            'model' => $this->model,
            'input' => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $user],
            ],
            'text' => ['format' => ['type' => 'json_object']],
        ];
        // Yalnız reasoning modelləri üçün — sürəti artırmaq üçün effort (məs. low).
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
