<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Deck;
use App\Services\DeckShareService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeckController extends Controller
{
    public function __construct(private readonly DeckShareService $shares) {}

    /** GET /api/v1/study/decks — istifadəçinin kolodaları + bugün due sayı */
    public function index(Request $request): JsonResponse
    {
        $decks = Deck::where('owner_uid', $request->user()->uid)->orderBy('name')->get();

        return response()->json(['data' => $decks->map(fn (Deck $d) => $this->payload($d))->all()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
            'template_uid' => ['nullable', 'string', 'exists:App\Models\CardTemplate,uid'],
        ]);

        $deck = Deck::create([
            'owner_uid' => $request->user()->uid,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'template_uid' => $data['template_uid'] ?? null,
        ]);

        return response()->json($this->payload($deck), 201);
    }

    public function update(Request $request, Deck $deck): JsonResponse
    {
        $this->authorizeOwner($request, $deck);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
            'template_uid' => ['sometimes', 'nullable', 'string', 'exists:App\Models\CardTemplate,uid'],
        ]);
        $deck->update($data);

        return response()->json($this->payload($deck));
    }

    public function destroy(Request $request, Deck $deck): JsonResponse
    {
        $this->authorizeOwner($request, $deck);
        $deck->delete();

        return response()->json(['message' => __('messages.deleted')]);
    }

    private function authorizeOwner(Request $request, Deck $deck): void
    {
        abort_unless($deck->owner_uid === $request->user()->uid, 403);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Deck $d): array
    {
        $today = now()->toDateString();

        return [
            'uid' => $d->uid,
            'name' => $d->name,
            'description' => $d->description,
            'template_uid' => $d->template_uid,
            'cards_total' => $d->cards()->count(),
            'due_count' => $d->cards()->whereDate('due', '<=', $today)->count(),
            'new_count' => $d->cards()->where('state', 'new')->count(),
            'imported' => $d->source_deck_uid !== null,
            'pending_updates' => $d->source_deck_uid !== null ? $this->shares->pendingUpdates($d) : 0,
        ];
    }
}
