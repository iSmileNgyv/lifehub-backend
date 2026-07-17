<?php

namespace App\Telegram\Modules;

use App\Models\Card;
use App\Models\CardTemplate;
use App\Models\Deck;
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
    /** @var array<string, CardTemplate|null> deck_uid → template */
    private array $tplCache = [];

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

    /** Növbəti due kartı göndər və ya "bitdi" (on-demand /learn üçün). */
    private function sendNext(TelegramContext $ctx, ?string $excludeUid = null): void
    {
        $card = $this->dueQuery($excludeUid)->first();
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
        $tpl = $this->templateFor($card);
        $buttons = [[['text' => '👁 Göstər', 'callback_data' => "st:show:{$card->uid}"]]];
        $text = $this->renderSide($card, $tpl, front: true);
        $img = $this->sideImage($card, $tpl, front: true);

        if ($img && ($bytes = $this->imageBytes($img))) {
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
        $tpl = $this->templateFor($card);
        $text = $this->renderSide($card, $tpl, front: false);
        $img = $this->sideImage($card, $tpl, front: false);

        if ($img && ($bytes = $this->imageBytes($img))) {
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

    /** Şablonda hər hansı sahə "Telegram ön"ə işarələnib? */
    private function hasTgFront(CardTemplate $tpl): bool
    {
        foreach ($tpl->fields as $f) {
            if (! empty($f['tgFront'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Bir tərəfi mətn kimi render et.
     * Şablonlu kart: ön = tgFront işarəli sahələr (yoxdursa side='front'); Göstər = qalanı (label: dəyər).
     * Sadə kart: front/back sütunları.
     */
    private function renderSide(Card $card, ?CardTemplate $tpl, bool $front): string
    {
        if ($card->fields && $tpl) {
            $useTg = $this->hasTgFront($tpl);
            $lines = [];
            foreach ($tpl->fields as $f) {
                $type = $f['type'] ?? 'text';
                $onFront = $useTg ? ! empty($f['tgFront']) : (($f['side'] ?? '') === 'front');
                if ($front !== $onFront) {
                    continue;
                }
                if ($type === 'heading') {
                    if (! $front) {
                        $lines[] = '<b>'.$this->clean($f['label'] ?? '').'</b>';
                    }

                    continue;
                }
                if ($type === 'image') {
                    continue;
                }
                $val = $card->fields[$f['key'] ?? ''] ?? null;
                if ($val === null || trim((string) $val) === '') {
                    continue;
                }
                $text = ($type === 'rich') ? $this->stripHtml((string) $val) : (string) $val;
                $lines[] = $front
                    ? $this->clean($text)
                    : '<b>'.$this->clean($f['label'] ?? '').':</b> '.$this->clean($text);
            }

            return implode($front ? ' · ' : "\n", $lines);
        }

        return $this->clean($front ? $card->front : $card->back);
    }

    /** Bir tərəfin şəkli (stored_file uid): sütun, yoxsa şablon image sahəsi. */
    private function sideImage(Card $card, ?CardTemplate $tpl, bool $front): ?string
    {
        $col = $front ? $card->front_image : $card->back_image;
        if ($col) {
            return $col;
        }
        if ($card->fields && $tpl) {
            $useTg = $this->hasTgFront($tpl);
            foreach ($tpl->fields as $f) {
                if (($f['type'] ?? '') !== 'image') {
                    continue;
                }
                $onFront = $useTg ? ! empty($f['tgFront']) : (($f['side'] ?? '') === 'front');
                if ($front !== $onFront) {
                    continue;
                }
                $val = $card->fields[$f['key'] ?? ''] ?? null;
                if ($val) {
                    return (string) $val;
                }
            }
        }

        return null;
    }

    private function stripHtml(string $s): string
    {
        return trim(preg_replace('/\s+/', ' ', strip_tags($s)) ?? '');
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
