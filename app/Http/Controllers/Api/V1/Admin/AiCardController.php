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
    /** Bulk-da bir sorğuya neçə söz (xərc/etibarlılıq balansı; kiçik = daha tam cavab). */
    private const BULK_CHUNK = 8;

    /** POST /study/templates/{template}/generate — bir söz üçün sahələri doldur. */
    public function generate(Request $request, CardTemplate $template): JsonResponse
    {
        $this->owner($request, $template);
        $data = $request->validate([
            'word' => ['required', 'string', 'max:500'],
            'only' => ['nullable', 'array'],       // yalnız bu açarları doldur (boşluqlar)
            'only.*' => ['string', 'max:60'],
        ]);

        $textFields = $this->textFields($template);
        if (! empty($data['only'])) {
            $textFields = array_values(array_filter($textFields, fn ($f) => in_array($f['key'], $data['only'], true)));
            if (empty($textFields)) {
                return response()->json(['fields' => []]);
            }
        }

        try {
            $fields = AiFactory::make()->generateFields($textFields, $data['word'], (string) $template->ai_instruction);
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
            'only' => ['nullable', 'array'],       // yalnız bu açarları doldur (boşluqlar)
            'only.*' => ['string', 'max:60'],
        ]);

        try {
            $provider = AiFactory::make();
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $textFields = $this->textFields($template);
        if (! empty($data['only'])) {
            $textFields = array_values(array_filter($textFields, fn ($f) => in_array($f['key'], $data['only'], true)));
        }
        $instruction = (string) $template->ai_instruction;
        $words = array_values(array_unique(array_map('trim', $data['words'])));

        // Xərci azaltmaq üçün bir sorğuda bir neçə söz (chunk).
        $results = [];
        foreach (array_chunk($words, self::BULK_CHUNK) as $chunk) {
            try {
                $batch = $provider->generateFieldsBatch($textFields, $chunk, $instruction);
                foreach ($chunk as $word) {
                    $results[] = ['word' => $word, 'fields' => $batch[$word] ?? [], 'error' => null];
                }
            } catch (Throwable $e) {
                foreach ($chunk as $word) {
                    $results[] = ['word' => $word, 'fields' => [], 'error' => $e->getMessage()];
                }
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
            ->map(fn ($f) => ['key' => $f['key'], 'label' => $f['label'] ?? '', 'description' => $f['description'] ?? null])
            ->values()
            ->all();
    }

    private function owner(Request $request, CardTemplate $template): void
    {
        abort_unless($template->owner_uid === $request->user()->uid, 403);
    }
}
