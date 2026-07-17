<?php

namespace App\Services;

use App\Enums\CashOrderType;
use App\Models\CashDesk;
use App\Models\CashLedgerEntry;
use App\Models\TradingJournal;
use App\Models\TradingLedgerEntry;
use App\Support\TransactionNumber;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Trading jurnalının postu — Procurement item journal güzgüsü, tək USD FIFO.
 * buy → FIFO təbəqə (USD giriş). sell → FIFO çıxış (COGS manatla).
 * Kassaya NET (satış−alış manat) BİR cash_ledger sətri. Mənfəət = satış − COGS.
 */
class TradingPostingService
{
    /**
     * Dry-run "Yoxla" — post ETMƏDƏN jurnalın cari statistikasını hesablayır (mənfəət daxil).
     * FIFO cari açıq təbəqələr + bu jurnalın alışları üzərində YADDAŞDA simulyasiya olunur (DB dəyişmir).
     *
     * @return array<string, float>
     */
    public function check(TradingJournal $journal): array
    {
        $ordered = $journal->entries()->get()
            ->sortBy(fn ($e) => $e->entry_type->value === 'buy' ? 0 : 1)->values();

        // Cari açıq FIFO təbəqələri (yaddaşa) — DB toxunulmur
        $layers = TradingLedgerEntry::where('positive', true)->where('open', true)->where('remain_qty', '>', 0)
            ->orderBy('posting_date')->orderBy('uid')->get()
            ->map(fn ($l) => ['remain' => (float) $l->remain_qty, 'unit' => (float) $l->unit_amount_lcy])
            ->all();

        $buyManat = 0.0;
        $sellManat = 0.0;
        $buyUsd = 0.0;
        $sellUsd = 0.0;
        $cogs = 0.0;
        $shortage = 0.0;

        foreach ($ordered as $e) {
            $usd = (float) $e->usd_qty;
            $manat = (float) $e->manat_amount;

            if ($e->entry_type->value === 'buy') {
                $layers[] = ['remain' => $usd, 'unit' => $usd > 0 ? $manat / $usd : 0];
                $buyManat += $manat;
                $buyUsd += $usd;
            } else {
                $remaining = $usd;
                foreach ($layers as &$layer) {
                    if ($remaining <= 0) {
                        break;
                    }
                    if ($layer['remain'] <= 0) {
                        continue;
                    }
                    $take = min($layer['remain'], $remaining);
                    $cogs += $take * $layer['unit'];
                    $layer['remain'] -= $take;
                    $remaining -= $take;
                }
                unset($layer);
                if ($remaining > 0) {
                    $shortage += $remaining;
                }
                $sellManat += $manat;
                $sellUsd += $usd;
            }
        }

        return [
            'buy_manat' => round($buyManat, 2),
            'sell_manat' => round($sellManat, 2),
            'buy_usd' => round($buyUsd, 4),
            'sell_usd' => round($sellUsd, 4),
            'net_cash' => round($sellManat - $buyManat, 2),
            'cogs' => round($cogs, 2),
            'profit' => round($sellManat - $cogs, 2),
            'shortage_usd' => round($shortage, 4), // >0 → USD çatmır, post edilə bilməz
        ];
    }

    /**
     * @return array<string, float>
     */
    public function post(TradingJournal $journal): array
    {
        if ($journal->status !== 'draft') {
            throw new RuntimeException('Jurnal artıq post olunub.');
        }
        if (! $journal->cash_desk_code) {
            throw new RuntimeException('Jurnalda kassa seçilməyib.');
        }

        $entries = $journal->entries()->get();
        if ($entries->isEmpty()) {
            throw new RuntimeException('Jurnal boşdur — post ediləcək sətir yoxdur.');
        }

        // Əvvəl alışlar (təbəqə yaransın), sonra satışlar (yeyə bilsin)
        $ordered = $entries->sortBy(fn ($e) => $e->entry_type->value === 'buy' ? 0 : 1)->values();

        $date = $journal->posting_date->toDateString();
        $user = auth()->user()?->username ?? $journal->resp_person;

        return DB::transaction(function () use ($journal, $ordered, $date, $user) {
            $txn = TransactionNumber::next();
            $buyManat = 0.0;
            $sellManat = 0.0;
            $cogs = 0.0;

            foreach ($ordered as $e) {
                $usd = (float) $e->usd_qty;
                $manat = (float) $e->manat_amount;

                if ($e->entry_type->value === 'buy') {
                    TradingLedgerEntry::create([
                        'transaction_number' => $txn,
                        'posting_date' => $date,
                        'doc_no' => $journal->code,
                        'journal_code' => $journal->code,
                        'entry_type' => 'buy',
                        'initial_qty' => $usd,
                        'remain_qty' => $usd,
                        'positive' => true,
                        'open' => true,
                        'unit_amount_lcy' => $usd > 0 ? round($manat / $usd, 4) : 0,
                        'amount_lcy' => round($manat, 2),
                        'resp_person' => $user,
                    ]);
                    $buyManat += $manat;
                } else {
                    $c = $this->consumeFifo($usd);
                    TradingLedgerEntry::create([
                        'transaction_number' => $txn,
                        'posting_date' => $date,
                        'doc_no' => $journal->code,
                        'journal_code' => $journal->code,
                        'entry_type' => 'sell',
                        'initial_qty' => $usd,
                        'remain_qty' => 0,
                        'positive' => false,
                        'open' => false,
                        'unit_amount_lcy' => $usd > 0 ? round($c / $usd, 4) : 0,
                        'amount_lcy' => $c,
                        'resp_person' => $user,
                    ]);
                    $sellManat += $manat;
                    $cogs += $c;
                }
            }

            // Kassaya NET (satış − alış) — bir hərəkət
            $net = round($sellManat - $buyManat, 2);
            if (abs($net) > 0.001) {
                $type = $net >= 0 ? CashOrderType::CashIn : CashOrderType::CashOut;
                $amount = abs($net);

                CashLedgerEntry::create([
                    'transaction_number' => $txn,
                    'posting_date' => $date,
                    'doc_no' => $journal->code,
                    'cash_desk_code' => $journal->cash_desk_code,
                    'amount_lcy' => $amount,
                    'entry_type' => $type->value,
                    'descr' => $journal->descr ?: 'Trading '.$journal->code,
                    'resp_person' => $user,
                ]);

                $desk = CashDesk::find($journal->cash_desk_code);
                if ($desk) {
                    $desk->balance_lcy = (float) $desk->balance_lcy + $type->sign() * $amount;
                    $desk->in_use = true;
                    $desk->save();
                }
            }

            $journal->update(['status' => 'posted', 'posted_at' => now()]);

            return [
                'revenue' => round($sellManat, 2),
                'buy_manat' => round($buyManat, 2),
                'cogs' => round($cogs, 2),
                'profit' => round($sellManat - $cogs, 2),
                'net_cash' => $net,
            ];
        });
    }

    /** FIFO çıxış — tək USD balansı (item/stock yox). Maya qaytarır. */
    private function consumeFifo(float $qty): float
    {
        $remaining = $qty;
        $cogs = 0.0;

        $layers = TradingLedgerEntry::where('positive', true)->where('open', true)->where('remain_qty', '>', 0)
            ->orderBy('posting_date')->orderBy('uid')->lockForUpdate()->get();

        foreach ($layers as $layer) {
            if ($remaining <= 0) {
                break;
            }
            $take = min((float) $layer->remain_qty, $remaining);
            $cogs += $take * (float) $layer->unit_amount_lcy;

            $newRemain = (float) $layer->remain_qty - $take;
            $layer->remain_qty = $newRemain;
            if ($newRemain <= 0) {
                $layer->open = false;
            }
            $layer->save();
            $remaining -= $take;
        }

        if ($remaining > 0) {
            throw new RuntimeException('USD balansı çatmır (çatışmır: '.round($remaining, 4).' $).');
        }

        return round($cogs, 2);
    }
}
