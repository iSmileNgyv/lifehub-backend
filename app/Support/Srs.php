<?php

namespace App\Support;

use App\Models\Card;

/**
 * SM-2 aralıqlı təkrar (Anki əsası). Reytinq: again / hard / good / easy.
 * again → sıfırla (bugün yenidən), ease−0.20. hard → ×1.2, ease−0.15.
 * good → ×ease. easy → ×ease×1.3, ease+0.15. İlk düz cavab 1 gün (easy 4). ease min 1.3.
 */
class Srs
{
    /**
     * @return array{state:string, interval:int, ease:float, reps:int, lapses:int, due:string}
     */
    public static function apply(Card $card, string $rating): array
    {
        $ease = (float) $card->ease;
        $interval = (int) $card->interval;
        $reps = (int) $card->reps;
        $lapses = (int) $card->lapses;

        if ($rating === 'again') {
            $ease = max(1.3, $ease - 0.20);
            $lapses++;
            $reps = 0;
            $interval = 0; // bugün yenidən
            $state = 'learning';
        } else {
            if ($reps === 0) {
                $interval = $rating === 'easy' ? 4 : 1;
            } elseif ($rating === 'hard') {
                $interval = max(1, (int) round($interval * 1.2));
                $ease = max(1.3, $ease - 0.15);
            } elseif ($rating === 'good') {
                $interval = max(1, (int) round($interval * $ease));
            } else { // easy
                $interval = max(1, (int) round($interval * $ease * 1.3));
                $ease += 0.15;
            }
            $reps++;
            $state = 'review';
        }

        return [
            'state' => $state,
            'interval' => $interval,
            'ease' => round($ease, 2),
            'reps' => $reps,
            'lapses' => $lapses,
            'due' => now()->addDays($interval)->toDateString(),
        ];
    }

    /**
     * Düymələr üçün növbəti aralıq (gün) önizləmə.
     *
     * @return array<string, int>
     */
    public static function preview(Card $card): array
    {
        $out = [];
        foreach (['again', 'hard', 'good', 'easy'] as $rating) {
            $out[$rating] = self::apply($card, $rating)['interval'];
        }

        return $out;
    }
}
