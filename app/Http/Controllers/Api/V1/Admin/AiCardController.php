<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\AI\AiFactory;
use App\Http\Controllers\Controller;
use App\Models\CardTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

class AiCardController extends Controller
{
    /** POST /study/templates/{template}/generate — bir söz üçün sahələri doldur. */
    public function generate(Request $request, CardTemplate $template): JsonResponse
    {
        $this->owner($request, $template);
        $data = $request->validate(['word' => ['required', 'string', 'max:500']]);

        try {
            $fields = AiFactory::make()->generateFields($this->textFields($template), $data['word'], (string) $template->ai_instruction);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['fields' => $fields]);
    }

    /** POST /study/templates/{template}/generate-bulk — söz siyahısı üçün kartlar. */
    public function generateBulk(Request $request, CardTemplate $template): JsonResponse
    {
        $this->owner($request, $template);
        $data = $request->validate([
            'words' => ['required', 'array', 'min:1', 'max:50'],
            'words.*' => ['required', 'string', 'max:500'],
        ]);

        try {
            $provider = AiFactory::make();
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $textFields = $this->textFields($template);
        $instruction = (string) $template->ai_instruction;
        $results = [];
        foreach (array_values(array_unique($data['words'])) as $word) {
            try {
                $results[] = ['word' => $word, 'fields' => $provider->generateFields($textFields, $word, $instruction), 'error' => null];
            } catch (Throwable $e) {
                $results[] = ['word' => $word, 'fields' => [], 'error' => $e->getMessage()];
            }
        }

        return response()->json(['results' => $results]);
    }

    /**
     * AI-nin dolduracağı sahələr (yalnız mətn/uzun mətn; şəkil sahələri istisna).
     *
     * @return array<int, array{key: string, description: ?string}>
     */
    private function textFields(CardTemplate $template): array
    {
        return collect($template->fields ?? [])
            ->filter(fn ($f) => in_array($f['type'] ?? 'text', ['text', 'textarea', 'rich'], true))
            ->map(fn ($f) => ['key' => $f['key'], 'description' => $f['description'] ?? null])
            ->values()
            ->all();
    }

    private function owner(Request $request, CardTemplate $template): void
    {
        abort_unless($template->owner_uid === $request->user()->uid, 403);
    }
}
