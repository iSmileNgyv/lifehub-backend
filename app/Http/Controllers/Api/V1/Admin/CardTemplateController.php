<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Card;
use App\Models\CardTemplate;
use App\Models\Deck;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CardTemplateController extends Controller
{
    /** GET /api/v1/study/templates/{template}/sample — önizləmə üçün nümunə kart (bu şablonlu). */
    public function sample(Request $request, CardTemplate $template): JsonResponse
    {
        $this->owner($request, $template);
        $deckUids = Deck::where('template_uid', $template->uid)->pluck('uid');
        $card = Card::whereIn('deck_uid', $deckUids)->whereNotNull('fields')->first()
            ?? Card::whereIn('deck_uid', $deckUids)->first();

        if (! $card) {
            return response()->json(['message' => __('messages.no_sample_card')], 404);
        }

        return response()->json(['fields' => $card->fields, 'front' => $card->front, 'back' => $card->back]);
    }

    /** GET /api/v1/study/templates */
    public function index(Request $request): JsonResponse
    {
        $templates = CardTemplate::where('owner_uid', $request->user()->uid)->orderBy('name')->get();

        return response()->json(['data' => $templates->map(fn (CardTemplate $t) => $this->payload($t))->all()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validateData($request);

        $template = CardTemplate::create([
            'owner_uid' => $request->user()->uid,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'ai_instruction' => $data['ai_instruction'] ?? null,
            'fields' => $data['fields'] ?? [],
            'display' => $data['display'] ?? null,
        ]);

        return response()->json($this->payload($template), 201);
    }

    public function update(Request $request, CardTemplate $template): JsonResponse
    {
        $this->owner($request, $template);
        $data = $this->validateData($request);

        $template->update([
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'ai_instruction' => $data['ai_instruction'] ?? null,
            'fields' => $data['fields'] ?? [],
            'display' => $data['display'] ?? null,
        ]);

        return response()->json($this->payload($template));
    }

    public function destroy(Request $request, CardTemplate $template): JsonResponse
    {
        $this->owner($request, $template);
        $template->delete();

        return response()->json(['message' => __('messages.deleted')]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
            'ai_instruction' => ['nullable', 'string', 'max:3000'],
            'fields' => ['sometimes', 'array'],
            'fields.*.key' => ['required', 'string', 'max:60'],
            'fields.*.label' => ['required', 'string', 'max:120'],
            'fields.*.description' => ['nullable', 'string', 'max:500'],
            'fields.*.type' => ['required', Rule::in(['text', 'textarea', 'rich', 'image', 'heading'])],
            'fields.*.side' => ['required', Rule::in(['front', 'back'])],
            'fields.*.section' => ['nullable', 'string', 'max:120'],
            'fields.*.list' => ['nullable', 'boolean'],   // kart siyahısında göstərilsin?
            'fields.*.tgFront' => ['nullable', 'boolean'], // Telegram bot ön mesajında göstər?
            // Kətan yerləşməsi (grid): x/y mövqe, w/h ölçü
            'fields.*.x' => ['nullable', 'integer', 'min:0', 'max:24'],
            'fields.*.y' => ['nullable', 'integer', 'min:0', 'max:400'],
            'fields.*.w' => ['nullable', 'integer', 'min:1', 'max:24'],
            'fields.*.h' => ['nullable', 'integer', 'min:1', 'max:50'],
            // Yalnız type='heading' üçün statik başlıq xüsusiyyətləri
            'fields.*.level' => ['nullable', Rule::in(['h1', 'h2', 'h3', 'h4'])],
            'fields.*.color' => ['nullable', 'string', 'max:32'],
            'fields.*.align' => ['nullable', Rule::in(['left', 'center', 'right'])],
            // Kanal görünüşü: {telegram:{front:[[key]],back:[[key]]}, extension:{...}} — sətirlər (yan-yana)
            'display' => ['nullable', 'array'],
        ]);
    }

    private function owner(Request $request, CardTemplate $template): void
    {
        abort_unless($template->owner_uid === $request->user()->uid, 403);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(CardTemplate $t): array
    {
        return [
            'uid' => $t->uid,
            'name' => $t->name,
            'description' => $t->description,
            'ai_instruction' => $t->ai_instruction,
            'fields' => $t->fields ?? [],
            'display' => $t->display,
        ];
    }
}
