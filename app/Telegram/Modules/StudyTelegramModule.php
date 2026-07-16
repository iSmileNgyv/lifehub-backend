<?php

namespace App\Telegram\Modules;

use App\Models\Card;
use App\Models\StoredFile;
use App\Models\TelegramSetting;
use App\Support\Srs;
use App\Telegram\Contracts\TelegramModule;
use App\Telegram\TelegramContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/** Study (flashcard/SM-2) — Telegram-dan öyrənmə: ön → Göstər → arxa + qiymət → növbəti. */
class StudyTelegramModule implements TelegramModule
{
    public function commands(): array
    {
        return ['learn'];
    }

    public function ownsCallback(string $data): bool
    {
        return str_starts_with($data, 'st:');
    }

    public function onCommand(TelegramContext $ctx, string $command, string $args): void
    {
        $this->sendNext($ctx);
    }

    public function onCallback(TelegramContext $ctx, string $data): void
    {
        $parts = explode(':', $data); // st:show:uid | st:rate:uid:rating | st:learn
        $action = $parts[1] ?? '';

        if ($action === 'learn') {
            $ctx->answer();
            $this->sendNext($ctx);
        } elseif ($action === 'show' && isset($parts[2])) {
            $this->reveal($ctx, $parts[2]);
        } elseif ($action === 'rate' && isset($parts[2], $parts[3])) {
            $this->rate($ctx, $parts[2], $parts[3]);
        } else {
            $ctx->answer();
        }
    }

    /** Due kartlar (owner-scoped, settings deck-inə görə) — state='new' sonra, due-ya görə. */
    private function dueQuery()
    {
        $deckUid = optional(TelegramSetting::find(Auth::user()->uid))->study_deck_uid;

        $q = Card::query()
            ->whereDate('due', '<=', now()->toDateString())
            ->orderByRaw("state = 'new'") // əvvəl təkrarlar, sonra yenilər
            ->orderBy('due');
        if ($deckUid) {
            $q->where('deck_uid', $deckUid);
        }

        return $q;
    }

    /** Növbəti due kartı göndər və ya "bitdi" (on-demand /learn üçün). */
    private function sendNext(TelegramContext $ctx): void
    {
        $card = $this->dueQuery()->first();
        if (! $card) {
            $ctx->say('🎉 Bugünlük bitdi — due kart yoxdur.');

            return;
        }
        $this->sendFront($ctx, $card);
    }

    /** Proaktiv push — N due kart göndər (yoxdursa səssiz). Qaytarır: göndərilən say. */
    public function pushDue(TelegramContext $ctx, int $count): int
    {
        $cards = $this->dueQuery()->limit(max(1, $count))->get();
        foreach ($cards as $card) {
            $this->sendFront($ctx, $card);
        }

        return $cards->count();
    }

    private function sendFront(TelegramContext $ctx, Card $card): void
    {
        $buttons = [[['text' => '👁 Göstər', 'callback_data' => "st:show:{$card->uid}"]]];
        $text = $this->clean($card->front);

        if ($card->front_image && ($bytes = $this->imageBytes($card->front_image))) {
            $ctx->photo($bytes, 'front.jpg', $text ?: null, $buttons);
        } else {
            $ctx->say($text !== '' ? $text : '—', $buttons);
        }
    }

    /** Göstər → arxa üzü + qiymət düymələri. */
    private function reveal(TelegramContext $ctx, string $uid): void
    {
        $card = Card::find($uid);
        if (! $card) {
            $ctx->answer('Tapılmadı');

            return;
        }
        $ctx->answer();
        $ctx->clearButtons(); // öndəki "Göstər"i sil

        $p = Srs::preview($card); // {again,hard,good,easy} gün
        $buttons = [
            [
                ['text' => '🔁 Yenidən', 'callback_data' => "st:rate:{$uid}:again"],
                ['text' => "😬 Çətin · {$p['hard']}g", 'callback_data' => "st:rate:{$uid}:hard"],
            ],
            [
                ['text' => "🙂 Yaxşı · {$p['good']}g", 'callback_data' => "st:rate:{$uid}:good"],
                ['text' => "😎 Asan · {$p['easy']}g", 'callback_data' => "st:rate:{$uid}:easy"],
            ],
        ];
        $text = $this->clean($card->back);

        if ($card->back_image && ($bytes = $this->imageBytes($card->back_image))) {
            $ctx->photo($bytes, 'back.jpg', $text ?: null, $buttons);
        } else {
            $ctx->say($text !== '' ? $text : '—', $buttons);
        }
    }

    /** Qiymət → SM-2 tətbiq et → növbəti kart. */
    private function rate(TelegramContext $ctx, string $uid, string $rating): void
    {
        if (! in_array($rating, ['again', 'hard', 'good', 'easy'], true)) {
            $ctx->answer();

            return;
        }
        $card = Card::find($uid);
        if (! $card) {
            $ctx->answer('Tapılmadı');

            return;
        }
        $card->update(Srs::apply($card, $rating));
        $ctx->answer("✓ {$card->interval} gün sonra");
        $ctx->clearButtons();
        $this->sendNext($ctx);
    }

    private function imageBytes(string $uid): ?string
    {
        $file = StoredFile::find($uid);
        if (! $file) {
            return null;
        }
        try {
            return Storage::disk('public')->get($file->path);
        } catch (\Throwable) {
            return null;
        }
    }

    /** Kart mətnini HTML üçün təhlükəsizləşdir (parse_mode=HTML). */
    private function clean(?string $s): string
    {
        return htmlspecialchars(trim((string) $s), ENT_QUOTES, 'UTF-8');
    }
}
