<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Card;
use App\Models\Deck;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CardController extends Controller
{
    /** GET /api/v1/study/decks/{deck}/cards */
    public function index(Request $request, Deck $deck): JsonResponse
    {
        $this->owner($request, $deck);
        $cards = $deck->cards()->orderByDesc('created_at')->get();

        return response()->json(['data' => $cards->map(fn (Card $c) => $this->payload($c))->all()]);
    }

    public function store(Request $request, Deck $deck): JsonResponse
    {
        $this->owner($request, $deck);
        $data = $this->validateData($request, $deck);

        $card = $deck->cards()->create([
            ...$data,
            'due' => now()->toDateString(),
            'state' => 'new',
        ]);

        return response()->json($this->payload($card), 201);
    }

    public function update(Request $request, Deck $deck, Card $card): JsonResponse
    {
        $this->owner($request, $deck);
        abort_unless($card->deck_uid === $deck->uid, 404);
        $card->update($this->validateData($request, $deck));

        return response()->json($this->payload($card));
    }

    public function destroy(Request $request, Deck $deck, Card $card): JsonResponse
    {
        $this->owner($request, $deck);
        abort_unless($card->deck_uid === $deck->uid, 404);
        $card->delete();

        return response()->json(['message' => __('messages.deleted')]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateData(Request $request, Deck $deck): array
    {
        // Şablonlu koloda → dinamik sahələr (fields = key→dəyər)
        if ($deck->template_uid) {
            $v = $request->validate([
                'fields' => ['required', 'array'],
            ]);

            return [
                'fields' => $v['fields'],
                'front' => null, 'back' => null,
                'front_image' => null, 'back_image' => null,
            ];
        }

        // Sadə ön/arxa (şablonsuz)
        $data = $request->validate([
            'front' => ['nullable', 'string', 'max:20000'],
            'back' => ['nullable', 'string', 'max:20000'],
            'front_image' => ['nullable', 'string', Rule::exists('stored_files', 'uid')],
            'back_image' => ['nullable', 'string', Rule::exists('stored_files', 'uid')],
        ]);

        $data['front'] = trim((string) ($data['front'] ?? ''));
        $data['back'] = trim((string) ($data['back'] ?? ''));

        // Hər tərəf ən azı mətn VƏ YA şəkil olmalıdır
        if ($data['front'] === '' && empty($data['front_image'])) {
            abort(response()->json(['message' => 'Ön tərəf: mətn və ya şəkil lazımdır.'], 422));
        }
        if ($data['back'] === '' && empty($data['back_image'])) {
            abort(response()->json(['message' => 'Arxa tərəf: mətn və ya şəkil lazımdır.'], 422));
        }

        $data['fields'] = null;

        return $data;
    }

    private function owner(Request $request, Deck $deck): void
    {
        abort_unless($deck->owner_uid === $request->user()->uid, 403);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Card $c): array
    {
        return [
            'uid' => $c->uid,
            'front' => $c->front,
            'back' => $c->back,
            'front_image' => $c->front_image,
            'back_image' => $c->back_image,
            'fields' => $c->fields,
            'state' => $c->state,
            'due' => $c->due?->toDateString(),
            'interval' => $c->interval,
            'reps' => $c->reps,
        ];
    }
}
