<?php

namespace App\Support;

use Carbon\Carbon;

/**
 * Probeq sürətini (km/gün) oxunuş logundan hesablayır — çəkili xətti reqressiya.
 * Real tarixləri işlədir (qeyri-bərabər aralar problem deyil). Yeni günlərə çox çəki (λ).
 */
class PaceEstimator
{
    private const LAMBDA = 0.97;    // təzəlik çəkisi (gün başına)

    private const WINDOW_DAYS = 60; // pəncərə

    /**
     * @param  iterable<mixed>  $readings  hər biri {reading_date, km} (model və ya array)
     * @return array{pace: ?float, current_km: ?float, as_of: ?string, readings: int}
     */
    public static function estimate(iterable $readings, ?float $fallback = null): array
    {
        $pts = [];
        foreach ($readings as $r) {
            $date = is_array($r) ? $r['reading_date'] : $r->reading_date;
            $km = is_array($r) ? $r['km'] : $r->km;
            $pts[] = ['ts' => Carbon::parse($date)->startOfDay()->timestamp, 'km' => (float) $km];
        }
        usort($pts, fn ($a, $b) => $a['ts'] <=> $b['ts']);
        $n = count($pts);

        if ($n === 0) {
            return ['pace' => $fallback, 'current_km' => null, 'as_of' => null, 'readings' => 0];
        }

        $last = $pts[$n - 1];
        $currentKm = $last['km'];
        $asOf = Carbon::createFromTimestamp($last['ts'])->toDateString();

        if ($n === 1) {
            return ['pace' => $fallback, 'current_km' => $currentKm, 'as_of' => $asOf, 'readings' => 1];
        }

        // Pəncərə: son WINDOW_DAYS gün (az data varsa hamısı)
        $winStart = $last['ts'] - self::WINDOW_DAYS * 86400;
        $win = array_values(array_filter($pts, fn ($p) => $p['ts'] >= $winStart));
        if (count($win) < 2) {
            $win = $pts;
        }

        // Çəkili least-squares slope (x = last-dən gün fərqi ≤ 0, w = λ^|x|)
        $sw = $swx = $swy = $swxx = $swxy = 0.0;
        foreach ($win as $p) {
            $x = ($p['ts'] - $last['ts']) / 86400.0;
            $y = $p['km'];
            $w = self::LAMBDA ** abs($x);
            $sw += $w;
            $swx += $w * $x;
            $swy += $w * $y;
            $swxx += $w * $x * $x;
            $swxy += $w * $x * $y;
        }
        $denom = $sw * $swxx - $swx * $swx;
        $pace = abs($denom) > 1e-9 ? ($sw * $swxy - $swx * $swy) / $denom : $fallback;

        // Odometr artan olmalıdır — mənfi meyl anlamsızdır
        if ($pace !== null && $pace < 0) {
            $pace = $fallback;
        }

        return [
            'pace' => $pace !== null ? round($pace, 3) : null,
            'current_km' => $currentKm,
            'as_of' => $asOf,
            'readings' => $n,
        ];
    }

    /** Bu günə təxmini cari km (son oxunuş + sürət × keçən gün). */
    public static function projectedKm(?float $currentKm, ?string $asOf, ?float $pace): ?float
    {
        if ($currentKm === null) {
            return null;
        }
        if (! $asOf || $pace === null) {
            return $currentKm;
        }
        $days = (now()->startOfDay()->timestamp - Carbon::parse($asOf)->startOfDay()->timestamp) / 86400.0;

        return $days > 0 ? round($currentKm + $pace * $days, 2) : $currentKm;
    }
}
