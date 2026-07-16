<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\FinanceLedgerLine;
use App\Models\Item;
use Illuminate\Http\JsonResponse;

/**
 * Məhsulun qiymət tarixçəsi — post olunmuş çek sətirlərindən (finance_ledger_line) çıxarılır.
 * Ayrıca cədvəl yoxdur; ledger özü tarixçədir. Hər variant (vahid + çəki) ayrı izlənir.
 */
class ItemPriceController extends Controller
{
    /** GET /api/v1/items/{item}/last-prices — hər variant üçün son qiymət (auto-fill üçün). */
    public function lastPrices(Item $item): JsonResponse
    {
        $rows = FinanceLedgerLine::where('item_code', $item->code)
            ->orderBy('posting_date')->orderBy('created_at')->get()
            ->groupBy(fn (FinanceLedgerLine $l) => ($l->measure_code ?? '').'|'.($l->meas_weight ?? ''))
            ->map(fn ($g) => $g->last()) // asc sıralı → sonuncu = ən son
            ->map(fn (FinanceLedgerLine $l) => [
                'measure_code' => $l->measure_code,
                'meas_weight' => $l->meas_weight,
                'unit_price' => $l->unit_price,
                'posting_date' => $l->posting_date?->toDateString(),
            ])
            ->values();

        return response()->json($rows);
    }

    /** GET /api/v1/items/{item}/price-history — variant üzrə qiymət DƏYİŞMƏLƏRİ (eyni qiymət təkrar yazılmır). */
    public function history(Item $item): JsonResponse
    {
        $byVariant = FinanceLedgerLine::where('item_code', $item->code)
            ->orderBy('posting_date')->orderBy('created_at')->get()
            ->groupBy(fn (FinanceLedgerLine $l) => ($l->measure_code ?? '').'|'.($l->meas_weight ?? ''));

        $result = $byVariant->map(function ($g) {
            $changes = [];
            $prev = null;
            foreach ($g as $l) { // asc
                if ($prev === null || (float) $l->unit_price !== (float) $prev) {
                    $changes[] = [
                        'posting_date' => $l->posting_date?->toDateString(),
                        'unit_price' => $l->unit_price,
                    ];
                }
                $prev = $l->unit_price;
            }
            $first = $g->first();

            return [
                'measure_code' => $first->measure_code,
                'meas_weight' => $first->meas_weight,
                'changes' => array_reverse($changes), // ən yeni yuxarıda
            ];
        })->values();

        return response()->json($result);
    }
}
