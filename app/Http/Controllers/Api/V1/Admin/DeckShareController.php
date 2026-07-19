<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Deck;
use App\Models\DeckShare;
use App\Services\DeckShareService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeckShareController extends Controller
{
    public function __construct(private readonly DeckShareService $service) {}

    /** GET /api/v1/study/decks/{deck}/share — cari paylaşım kodu (varsa) */
    public function show(Request $request, Deck $deck): JsonResponse
    {
        $this->owner($request, $deck);
        $share = DeckShare::where('deck_uid', $deck->uid)->whereNull('revoked_at')->first();

        return response()->json(['code' => $share?->code]);
    }

    /** POST /api/v1/study/decks/{deck}/share — paylaşım kodu yarat/qaytar */
    public function store(Request $request, Deck $deck): JsonResponse
    {
        $this->owner($request, $deck);
        $share = $this->service->createShare($deck);

        return response()->json(['code' => $share->code], 201);
    }

    /** DELETE /api/v1/study/decks/{deck}/share — paylaşımı dayandır */
    public function destroy(Request $request, Deck $deck): JsonResponse
    {
        $this->owner($request, $deck);
        DeckShare::where('deck_uid', $deck->uid)->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        return response()->json(['message' => __('messages.deleted')]);
    }

    /** POST /api/v1/study/import — kod üzrə kolodanı öz hesabına idxal et */
    public function import(Request $request): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:32']]);
        $deck = $this->service->import($data['code'], $request->user());

        return response()->json(['uid' => $deck->uid, 'name' => $deck->name], 201);
    }

    /** POST /api/v1/study/decks/{deck}/pull — mənbədən yeni kartları gətir */
    public function pull(Request $request, Deck $deck): JsonResponse
    {
        $this->owner($request, $deck);
        $added = $this->service->pullUpdates($deck);

        return response()->json(['added' => $added]);
    }

    private function owner(Request $request, Deck $deck): void
    {
        abort_unless($deck->owner_uid === $request->user()->uid, 403);
    }
}
