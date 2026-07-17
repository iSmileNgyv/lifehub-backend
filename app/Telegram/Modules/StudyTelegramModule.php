<?php

namespace App\Telegram\Modules;

use App\Models\Card;
use App\Models\CardTemplate;
use App\Models\Deck;
use App\Models\StoredFile;
use App\Models\TelegramSetting;
use App\Support\CardRenderer;
use App\Support\Srs;
use App\Telegram\Contracts\TelegramModule;
use App\Telegram\TelegramContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

/** Study (flashcard/SM-2) — Telegram-dan öyrənmə: ön → Göstər → arxa + qiymət → növbəti. */
class StudyTelegramModule implements TelegramModule
{
    /** @var array<string, CardTemplate|null> deck_uid → template */
    private array $tplCache = [];

    public function key(): string
    {
        return 'study';
    }

    public function menuButton(): ?array
    {
        return ['text' => '📚 Öyrən', 'callback_data' => 'st:learn', 'op' => 'STUDY_VIEW'];
    }

    public function commands(): array
    {
        return ['learn'];
    }

    public function ownsCallback(string $data): bool
    {
        return str_starts_with($data, 'st:');
    }

    public function onText(TelegramContext $ctx, string $text): void
    {
        // Study mətn addımı işlətmir (callback-əsaslıdır).
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
        } elseif ($action === 'next' && isset($parts[2])) {
            $ctx->answer();
            $ctx->clearButtons();
            $this->sendNext($ctx, $parts[2]); // öyrənmə modu: növbəti (cari istisna)
        } elseif ($action === 'show' && isset($parts[2])) {
            $this->reveal($ctx, $parts[2]);
        } elseif ($action === 'rate' && isset($parts[2], $parts[3])) {
            $this->rate($ctx, $parts[2], $parts[3]);
        } else {
            $ctx->answer();
        }
    }

    /** Due kartlar (owner-scoped, settings deck-inə görə) — state='new' sonra, due-ya görə. */
    private function dueQuery(?string $excludeUid = null)
    {
        $deckUid = optional(TelegramSetting::find(Auth::user()->uid))->study_deck_uid;

        $q = Card::query()
            ->whereDate('due', '<=', now()->toDateString())
            ->orderByRaw("state = 'new'") // əvvəl təkrarlar, sonra yenilər
            ->orderBy('due');
        if ($deckUid) {
            $q->where('deck_uid', $deckUid);
        }
        // Yenicə qiymətləndirilən kartı istisna et — "again" (due=bu gün) dərhal geri gəlməsin,
        // digərlərindən sonra qayıtsın (appdakı sessiya re-queue-nun analoqu).
        if ($excludeUid) {
            $q->where('uid', '!=', $excludeUid);
        }

        return $q;
    }

    private function mode(): string
    {
        return optional(TelegramSetting::find(Auth::user()->uid))->mode ?? 'flashcard';
    }

    /** Növbəti due kartı göndər və ya "bitdi" (rejimə görə). */
    private function sendNext(TelegramContext $ctx, ?string $excludeUid = null): void
    {
        $card = $this->dueQuery($excludeUid)->first();
        if (! $card) {
            $ctx->say('🎉 Bugünlük bitdi — due kart yoxdur.');

            return;
        }
        if ($this->mode() === 'learning') {
            $this->sendLearning($ctx, $card);
        } else {
            $this->sendFront($ctx, $card);
        }
    }

    /** Öyrənmə modu: soruşmadan tam kartı göstər (ön + arxa, sıralı) + Növbəti. */
    private function sendLearning(TelegramContext $ctx, Card $card): void
    {
        $r = (new CardRenderer)->render($card, $this->templateFor($card), 'telegram');
        $front = $this->clean($r['front']);
        $back = $this->clean($r['back']);
        $text = trim($front.($back !== '' ? "\n———\n".$back : ''));
        $buttons = [[['text' => '➡️ Növbəti', 'callback_data' => "st:next:{$card->uid}"]]];
        $img = $r['front_image'] ?: $r['back_image'];

        if ($img && ($bytes = $this->imageBytes($img))) {
            $ctx->photo($bytes, 'card.jpg', $text ?: null, $buttons);
        } else {
            $ctx->say($text !== '' ? $text : '—', $buttons);
        }
    }

    /** Proaktiv push — N due kart göndər (yoxdursa səssiz). Qaytarır: göndərilən say. */
    public function pushDue(TelegramContext $ctx, int $count): int
    {
        $cards = $this->dueQuery()->limit(max(1, $count))->get();
        $learning = $this->mode() === 'learning';
        foreach ($cards as $card) {
            $learning ? $this->sendLearning($ctx, $card) : $this->sendFront($ctx, $card);
        }

        return $cards->count();
    }

    private function sendFront(TelegramContext $ctx, Card $card): void
    {
        $r = (new CardRenderer)->render($card, $this->templateFor($card), 'telegram');
        $buttons = [[['text' => '👁 Göstər', 'callback_data' => "st:show:{$card->uid}"]]];
        $text = $this->clean($r['front']);

        if ($r['front_image'] && ($bytes = $this->imageBytes($r['front_image']))) {
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
        $r = (new CardRenderer)->render($card, $this->templateFor($card), 'telegram');
        $text = $this->clean($r['back']);

        if ($r['back_image'] && ($bytes = $this->imageBytes($r['back_image']))) {
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
        $ctx->answer($rating === 'again' ? '🔁 Yenidən sıraya' : "✓ {$card->interval} gün sonra");
        $ctx->clearButtons();
        $this->sendNext($ctx, $uid); // eyni kartı dərhal təkrar göndərmə
    }

    /** Kartın deck şablonu (varsa). Owner-scoped. */
    private function templateFor(Card $card): ?CardTemplate
    {
        if (array_key_exists($card->deck_uid, $this->tplCache)) {
            return $this->tplCache[$card->deck_uid];
        }
        $deck = Deck::find($card->deck_uid);
        $tpl = $deck && $deck->template_uid ? CardTemplate::find($deck->template_uid) : null;

        return $this->tplCache[$card->deck_uid] = $tpl;
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
