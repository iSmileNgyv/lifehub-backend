<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Card;
use App\Models\Deck;
use App\Support\Srs;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudyController extends Controller
{
    /** GET /api/v1/study/decks/{deck}/queue — bugün due kartlar (öyrənmə növbəsi) */
    public function queue(Request $request, Deck $deck): JsonResponse
    {
        abort_unless($deck->owner_uid === $request->user()->uid, 403);

        $cards = $deck->cards()
            ->whereDate('due', '<=', now()->toDateString())
            ->orderByRaw("state = 'new'") // əvvəl təkrarlar, sonra yenilər
            ->orderBy('due')
            ->limit(200)->get();

        return response()->json([
            'data' => $cards->map(fn (Card $c) => [
                'uid' => $c->uid,
                'front' => $c->front,
                'back' => $c->back,
                'front_image' => $c->front_image,
                'back_image' => $c->back_image,
                'fields' => $c->fields,
                'state' => $c->state,
                'preview' => Srs::preview($c), // düymələr üçün: {again,hard,good,easy} gün
            ])->all(),
        ]);
    }

    /** POST /api/v1/study/decks/{deck}/cards/{card}/answer — reytinq → SM-2 */
    public function answer(Request $request, Deck $deck, Card $card): JsonResponse
    {
        abort_unless($deck->owner_uid === $request->user()->uid, 403);
        abort_unless($card->deck_uid === $deck->uid, 404);

        $data = $request->validate([
            'rating' => ['required', 'in:again,hard,good,easy'],
        ]);

        $card->update(Srs::apply($card, $data['rating']));

        return response()->json(['due' => $card->due->toDateString(), 'interval' => $card->interval]);
    }
}
