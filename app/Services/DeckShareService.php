<?php

namespace App\Services;

use App\Models\Card;
use App\Models\CardTemplate;
use App\Models\Deck;
use App\Models\DeckShare;
use App\Models\StoredFile;
use App\Models\User;
use App\Storage\StorageFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Koloda paylaşımı — copy/fork semantikası.
 *
 * Paylaşım "live reference" DEYİL: idxal edən istifadəçidə tam müstəqil kopya (öz owner_uid-i ilə)
 * yaranır. Bir tərəfin dəyişikliyi digərinə təsir etmir. Yeganə əlaqə nəsil izidir (source_*),
 * ondan yalnız təkrar idxalda (yenilə) yeni kartları əlavə etmək üçün istifadə olunur.
 */
class DeckShareService
{
    /**
     * Koloda üçün aktiv paylaşım kodu qaytarır — yoxdursa yaradır (idempotent).
     */
    public function createShare(Deck $deck): DeckShare
    {
        $existing = DeckShare::where('deck_uid', $deck->uid)->whereNull('revoked_at')->first();
        if ($existing) {
            return $existing;
        }

        return DeckShare::create([
            'code' => $this->uniqueCode(),
            'deck_uid' => $deck->uid,
            'owner_uid' => $deck->owner_uid,
        ]);
    }

    /**
     * Kod üzrə kolodanı cari istifadəçinin hesabına müstəqil kopya kimi idxal edir.
     * Kart SRS vəziyyəti sıfırlanır (idxal edən sıfırdan öyrənir).
     */
    public function import(string $code, User $user): Deck
    {
        $share = $this->activeShare($code);
        $source = $this->sourceDeck($share->deck_uid);

        return DB::transaction(function () use ($share, $source, $user) {
            $templateUid = $this->copyTemplate($source->template_uid, $user);

            $copy = Deck::create([
                'owner_uid' => $user->uid,
                'name' => $source->name,
                'description' => $source->description,
                'template_uid' => $templateUid,
                'source_deck_uid' => $source->uid,
                'source_share_code' => $share->code,
            ]);

            foreach ($this->sourceCards($source->uid) as $card) {
                $this->copyCard($card, $copy->uid);
            }

            return $copy;
        });
    }

    /**
     * Mənbədə sonradan əlavə olunmuş kartları kopyaya gətirir (progress itmir).
     * Yalnız mənbədə olub kopyada hələ olmayan kartlar əlavə olunur; mövcud kartlara toxunulmur.
     *
     * @return int əlavə olunmuş kart sayı
     */
    public function pullUpdates(Deck $copy): int
    {
        abort_if($copy->source_deck_uid === null, 422, 'Bu koloda idxal olunmayıb.');
        // Mənbə koloda üçün hələ aktiv paylaşım varmı (sahib dayandıra bilər).
        // Konkret idxal koduna bağlamırıq — sahib təzədən paylaşsa yeni kod yaranır.
        $hasActive = DeckShare::where('deck_uid', $copy->source_deck_uid)->whereNull('revoked_at')->exists();
        abort_unless($hasActive, 403, 'Bu kolodanın paylaşımı dayandırılıb.');
        $source = $this->sourceDeck($copy->source_deck_uid);

        $have = Card::withoutGlobalScope('owner')
            ->where('deck_uid', $copy->uid)
            ->whereNotNull('source_card_uid')
            ->pluck('source_card_uid')
            ->all();
        $have = array_flip($have);

        return DB::transaction(function () use ($source, $copy, $have) {
            $added = 0;
            foreach ($this->sourceCards($source->uid) as $card) {
                if (isset($have[$card->uid])) {
                    continue;
                }
                $this->copyCard($card, $copy->uid);
                $added++;
            }

            return $added;
        });
    }

    /**
     * Mənbədə kopyaya hələ əlavə olunmamış kart sayı (frontend "yenilə" badge üçün).
     */
    public function pendingUpdates(Deck $copy): int
    {
        if ($copy->source_deck_uid === null) {
            return 0;
        }
        $share = DeckShare::where('deck_uid', $copy->source_deck_uid)->whereNull('revoked_at')->first();
        if (! $share) {
            return 0; // paylaşım dayandırılıb → yeniləmə mümkün deyil
        }

        $copied = Card::withoutGlobalScope('owner')
            ->where('deck_uid', $copy->uid)
            ->whereNotNull('source_card_uid')
            ->pluck('source_card_uid');

        // Mənbədə olub kopyada hələ istinad edilməyən kartların sayı
        return Card::withoutGlobalScope('owner')
            ->where('deck_uid', $copy->source_deck_uid)
            ->when($copied->isNotEmpty(), fn ($q) => $q->whereNotIn('uid', $copied))
            ->count();
    }

    // ── daxili köməkçilər ─────────────────────────────────────────────

    private function activeShare(?string $code): DeckShare
    {
        $share = $code ? DeckShare::find($code) : null;
        abort_if($share === null || ! $share->isActive(), 404, 'Paylaşım kodu tapılmadı və ya dayandırılıb.');

        return $share;
    }

    private function sourceDeck(string $uid): Deck
    {
        $deck = Deck::withoutGlobalScope('owner')->find($uid);
        abort_if($deck === null, 404, 'Mənbə koloda tapılmadı.');

        return $deck;
    }

    /**
     * @return \Illuminate\Support\Collection<int, Card>
     */
    private function sourceCards(string $deckUid)
    {
        return Card::withoutGlobalScope('owner')->where('deck_uid', $deckUid)->orderBy('created_at')->get();
    }

    private function copyCard(Card $src, string $deckUid): void
    {
        Card::create([
            'deck_uid' => $deckUid,
            'source_card_uid' => $src->uid,
            'front' => $src->front,
            'back' => $src->back,
            'front_image' => $this->duplicateFile($src->front_image),
            'back_image' => $this->duplicateFile($src->back_image),
            'fields' => $src->fields,
            // SRS sıfırlanır — idxal edən sıfırdan öyrənir
            'state' => 'new',
            'due' => now()->toDateString(),
            'interval' => 0,
            'ease' => 2.50,
            'reps' => 0,
            'lapses' => 0,
        ]);
    }

    /**
     * Şablonu idxal edən üçün kopyalayır (əgər deck şablonludursa).
     * Eyni mənbə şablonu artıq kopyalanıbsa təkrar yaratmır (dedup) — beləcə eyni şablonlu
     * bir neçə koloda idxal edildikdə şablon təkrarlanmır və kart sahələri (fields açarları) uyğun qalır.
     */
    private function copyTemplate(?string $sourceTemplateUid, User $user): ?string
    {
        if ($sourceTemplateUid === null) {
            return null;
        }

        $existing = CardTemplate::where('source_template_uid', $sourceTemplateUid)->first();
        if ($existing) {
            return $existing->uid;
        }

        $src = CardTemplate::withoutGlobalScope('owner')->find($sourceTemplateUid);
        if ($src === null) {
            return null; // mənbə şablon silinib → şablonsuz davam
        }

        $copy = CardTemplate::create([
            'owner_uid' => $user->uid,
            'source_template_uid' => $src->uid,
            'name' => $src->name,
            'description' => $src->description,
            'ai_instruction' => $src->ai_instruction,
            'fields' => $src->fields,
            'display' => $src->display,
        ]);

        return $copy->uid;
    }

    /**
     * Şəkil faylını fiziki olaraq kopyalayır (tam izolyasiya — mənbə faylı silsə kopya sınmır).
     */
    private function duplicateFile(?string $uid): ?string
    {
        if ($uid === null) {
            return null;
        }

        $src = StoredFile::find($uid);
        if ($src === null) {
            return null;
        }

        $driver = StorageFactory::make($src->driver);
        $contents = $driver->get($src->path);

        $newUid = (string) Str::ulid();
        $ext = pathinfo($src->path, PATHINFO_EXTENSION) ?: 'bin';
        $newPath = "uploads/{$newUid}.{$ext}";
        $driver->put($newPath, $contents);

        StoredFile::create([
            'uid' => $newUid,
            'driver' => $src->driver,
            'path' => $newPath,
            'original_name' => $src->original_name,
            'mime' => $src->mime,
            'size' => $src->size,
        ]);

        return $newUid;
    }

    private function uniqueCode(): string
    {
        do {
            // ambiguous simvollar (0/O, 1/I) çıxarılıb
            $code = substr(str_shuffle(str_repeat('ABCDEFGHJKLMNPQRSTUVWXYZ23456789', 3)), 0, 8);
        } while (DeckShare::find($code) !== null);

        return $code;
    }
}
