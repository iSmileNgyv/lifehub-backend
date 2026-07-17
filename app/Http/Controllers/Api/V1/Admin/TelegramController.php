<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\TelegramSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** Telegram bağlama + study push konfiqurasiyası (istifadəçi öz botunu idarə edir). */
class TelegramController extends Controller
{
    /** GET /api/v1/telegram — bağlantı statusu + push settings. */
    public function show(Request $request): JsonResponse
    {
        $user = $request->user();
        $s = TelegramSetting::find($user->uid);

        return response()->json([
            'linked' => ! empty($user->telegram_chat_id),
            'bot_username' => config('services.telegram.username'),
            'settings' => $this->settingsPayload($s),
        ]);
    }

    /** POST /api/v1/telegram/link-code — birdəfəlik bağlama kodu yarat. */
    public function linkCode(Request $request): JsonResponse
    {
        $user = $request->user();
        $code = strtoupper(Str::random(8));
        $user->forceFill([
            'telegram_link_code' => $code,
            'telegram_link_expires_at' => now()->addMinutes(15),
        ])->save();

        return response()->json(['code' => $code, 'bot_username' => config('services.telegram.username'), 'expires_min' => 15]);
    }

    /** POST /api/v1/telegram/unlink — chat bağlantısını ayır. */
    public function unlink(Request $request): JsonResponse
    {
        $request->user()->forceFill([
            'telegram_chat_id' => null,
            'telegram_link_code' => null,
            'telegram_link_expires_at' => null,
        ])->save();

        return response()->json(['ok' => true]);
    }

    /** PUT /api/v1/telegram/settings — study push konfiqurasiyası. */
    public function saveSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'study_enabled' => ['required', 'boolean'],
            'study_deck_uid' => ['nullable', 'string', Rule::exists('decks', 'uid')],
            'interval_min' => ['required', 'integer', 'min:5', 'max:1440'],
            'active_from' => ['required', 'date_format:H:i'],
            'active_to' => ['required', 'date_format:H:i'],
            'cards_per_push' => ['required', 'integer', 'min:1', 'max:10'],
        ]);

        $s = TelegramSetting::updateOrCreate(
            ['owner_uid' => $request->user()->uid],
            $data,
        );

        return response()->json(['settings' => $this->settingsPayload($s)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function settingsPayload(?TelegramSetting $s): array
    {
        return [
            'study_enabled' => (bool) ($s->study_enabled ?? false),
            'study_deck_uid' => $s->study_deck_uid ?? null,
            'interval_min' => (int) ($s->interval_min ?? 60),
            'active_from' => substr((string) ($s->active_from ?? '09:00'), 0, 5),
            'active_to' => substr((string) ($s->active_to ?? '22:00'), 0, 5),
            'cards_per_push' => (int) ($s->cards_per_push ?? 1),
        ];
    }
}
