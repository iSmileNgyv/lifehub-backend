<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Card;
use App\Models\CardTemplate;
use App\Models\Deck;
use App\Models\TelegramSetting;
use App\Support\CardRenderer;
use App\Support\Srs;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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

        $tpl = $deck->template_uid ? CardTemplate::find($deck->template_uid) : null;
        $renderer = new CardRenderer;

        return response()->json([
            'data' => $cards->map(function (Card $c) use ($tpl, $renderer) {
                $r = $renderer->render($c, $tpl, 'extension'); // extension görünüşü (dizayner)

                return [
                    'uid' => $c->uid,
                    'front' => $c->front,
                    'back' => $c->back,
                    'front_image' => $c->front_image,
                    'back_image' => $c->back_image,
                    'fields' => $c->fields,
                    'front_text' => $r['front'],   // sıralı mətn (template düzülüşünə görə)
                    'back_text' => $r['back'],
                    'front_rows' => $r['front_rows'], // yan-yana sətirlər (extension)
                    'back_rows' => $r['back_rows'],
                    'render_front_image' => $r['front_image'],
                    'render_back_image' => $r['back_image'],
                    'state' => $c->state,
                    'preview' => Srs::preview($c), // düymələr üçün: {again,hard,good,easy} gün
                ];
            })->all(),
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

    /** GET /api/v1/study/settings — öyrənmə parametrləri (bot + extension ortaq). */
    public function settings(Request $request): JsonResponse
    {
        return response()->json($this->settingsPayload(TelegramSetting::find($request->user()->uid)));
    }

    /** PUT /api/v1/study/settings — parametrləri yadda saxla. */
    public function saveSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'mode' => ['required', 'in:learning,flashcard'],
            'ext_mode' => ['required', 'in:learning,flashcard'],
            'study_deck_uid' => ['nullable', 'string', Rule::exists('decks', 'uid')],
            'active_from' => ['required', 'date_format:H:i'],
            'active_to' => ['required', 'date_format:H:i'],
            // Telegram push
            'study_enabled' => ['required', 'boolean'],
            'interval_min' => ['required', 'integer', 'min:5', 'max:1440'],
            'cards_per_push' => ['required', 'integer', 'min:1', 'max:10'],
            // Extension
            'ext_enabled' => ['required', 'boolean'],
            'ext_rotate_sec' => ['required', 'integer', 'min:5', 'max:3600'],
            'ext_notify' => ['required', 'boolean'],
            'ext_notify_min' => ['required', 'integer', 'min:1', 'max:1440'],
        ]);

        $s = TelegramSetting::updateOrCreate(['owner_uid' => $request->user()->uid], $data);

        return response()->json($this->settingsPayload($s));
    }

    /**
     * @return array<string, mixed>
     */
    private function settingsPayload(?TelegramSetting $s): array
    {
        return [
            'mode' => $s->mode ?? 'flashcard',
            'ext_mode' => $s->ext_mode ?? ($s->mode ?? 'flashcard'),
            'study_deck_uid' => $s->study_deck_uid ?? null,
            'active_from' => substr((string) ($s->active_from ?? '09:00'), 0, 5),
            'active_to' => substr((string) ($s->active_to ?? '22:00'), 0, 5),
            'study_enabled' => (bool) ($s->study_enabled ?? false),
            'interval_min' => (int) ($s->interval_min ?? 60),
            'cards_per_push' => (int) ($s->cards_per_push ?? 1),
            'ext_enabled' => (bool) ($s->ext_enabled ?? true),
            'ext_rotate_sec' => (int) ($s->ext_rotate_sec ?? 45),
            'ext_notify' => (bool) ($s->ext_notify ?? true),
            'ext_notify_min' => (int) ($s->ext_notify_min ?? 20),
        ];
    }
}
