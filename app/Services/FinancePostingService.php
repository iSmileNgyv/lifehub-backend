<?php

namespace App\Services;

use App\Enums\CashOrderType;
use App\Enums\FinanceEntryType;
use App\Models\CashDesk;
use App\Models\CashLedgerEntry;
use App\Models\FinanceJournal;
use App\Models\FinanceJournalEntry;
use App\Models\FinanceJournalLine;
use App\Models\FinanceLedgerEntry;
use App\Models\FinanceLedgerLine;
use App\Models\Item;
use App\Models\ItemMeasurement;
use App\Support\TransactionNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Maliyyə jurnalını post edir. Hər draft sətir → finance_ledger_entry (detal) + cash_ledger_entry (pul).
 * İkisi eyni transaction_number ilə bağlı. Post-dan sonra draft sətirlər silinir, jurnal başlığı qalır.
 * posting_date = jurnalın journal_date-i.
 */
class FinancePostingService
{
    /** Jurnalı post et — qaytarır: post olunan sətir sayı. */
    public function post(FinanceJournal $journal, ?string $user): int
    {
        $entries = $journal->entries()->orderBy('created_at')->get();
        if ($entries->isEmpty()) {
            throw new RuntimeException('Jurnal boşdur — post ediləcək sətir yoxdur.');
        }
        $fallback = $journal->journal_date->toDateString();

        return DB::transaction(function () use ($journal, $entries, $user, $fallback) {
            $count = 0;
            foreach ($entries as $entry) {
                // Hər sətir öz tarixinə post olunur (yoxdursa jurnal tarixi)
                $date = $entry->posting_date?->toDateString() ?? $fallback;
                $this->postEntry($journal, $entry, $date, $user);
                $entry->delete();
                $count++;
            }

            return $count;
        });
    }

    private function postEntry(FinanceJournal $journal, FinanceJournalEntry $entry, string $date, ?string $user): void
    {
        $amount = round((float) $entry->amount_lcy, 2);
        if ($amount <= 0) {
            throw new RuntimeException('Məbləğ 0-dan böyük olmalıdır.');
        }

        $txn = TransactionNumber::next();
        $type = $entry->entry_type;

        // Transfer: yalnız pul hərəkəti (mənbədən çıxış + hədəfə giriş), finance_ledger-ə GİRMİR
        if ($type === FinanceEntryType::Transfer) {
            if (! $entry->to_cash_desk_code || $entry->to_cash_desk_code === $entry->cash_desk_code) {
                throw new RuntimeException('Transfer üçün fərqli hədəf hesab lazımdır.');
            }
            $this->cashMove($txn, $date, $journal->code, $entry->cash_desk_code, CashOrderType::CashOut, $amount, $entry->descr, $user);
            $this->cashMove($txn, $date, $journal->code, $entry->to_cash_desk_code, CashOrderType::CashIn, $amount, $entry->descr, $user);

            return;
        }

        // Gəlir/xərc: detal ledger (kateqoriya + amount) + pul tərəfi
        $ledger = FinanceLedgerEntry::create([
            'transaction_number' => $txn,
            'posting_date' => $date,
            'jnl_code' => $journal->code,
            'entry_type' => $type->value,
            'cash_desk_code' => $entry->cash_desk_code,
            'category_code' => $entry->category_code,
            'amount_lcy' => $amount,
            'descr' => $entry->descr,
            'resp_person' => $user,
        ]);

        // Məhsul sətirləri (çek) varsa → finance_ledger_line-a köçür
        foreach ($entry->lines()->orderBy('sort_order')->get() as $line) {
            FinanceLedgerLine::create([
                'ledger_entry_uid' => $ledger->uid,
                'posting_date' => $date,
                'item_code' => $line->item_code,
                'item_name' => $line->item_name,
                'measure_code' => $line->measure_code,
                'meas_weight' => $line->meas_weight,
                'qty' => $line->qty,
                'unit_price' => $line->unit_price,
                'amount_lcy' => $line->amount_lcy,
            ]);
        }

        $this->cashMove($txn, $date, $journal->code, $entry->cash_desk_code, $type->cashDirection(), $amount, $entry->descr, $user);
    }

    /**
     * Post olunmuş ledger sətrini GERİ QAYTAR (unpost): kassanı geri al, ledger yazılarını sil,
     * draft entry-ni öz jurnalında (yoxdursa yeni jurnal) bərpa et. Qaytarır: bərpa olunan draft entry.
     * Yalnız gəlir/xərc (finance_ledger_entry). Transferlər burda deyil (yalnız kassada).
     */
    public function reverse(FinanceLedgerEntry $ledger, ?string $user): FinanceJournalEntry
    {
        return DB::transaction(function () use ($ledger, $user) {
            $txn = $ledger->transaction_number;

            // 1) Jurnal (yoxdursa posting_date ilə yeni yarat)
            $journal = ($ledger->jnl_code ? FinanceJournal::find($ledger->jnl_code) : null)
                ?? FinanceJournal::create([
                    'code' => 'FJ'.strtoupper(substr((string) Str::ulid(), -8)),
                    'journal_date' => $ledger->posting_date->toDateString(),
                    'resp_person' => $user,
                ]);

            // 2) Draft entry bərpa
            $entry = FinanceJournalEntry::create([
                'jnl_code' => $journal->code,
                'posting_date' => $ledger->posting_date->toDateString(),
                'entry_type' => $ledger->entry_type->value,
                'cash_desk_code' => $ledger->cash_desk_code,
                'to_cash_desk_code' => null,
                'category_code' => $ledger->category_code,
                'amount_lcy' => $ledger->amount_lcy,
                'descr' => $ledger->descr,
                'resp_person' => $user,
            ]);

            // 3) Məhsul sətirləri (çek) bərpa
            foreach ($ledger->lines()->orderBy('created_at')->get()->values() as $i => $line) {
                FinanceJournalLine::create([
                    'entry_uid' => $entry->uid,
                    'item_code' => $line->item_code,
                    'item_name' => $line->item_name,
                    'measure_code' => $line->measure_code,
                    'meas_weight' => $line->meas_weight,
                    'qty' => $line->qty,
                    'unit_price' => $line->unit_price,
                    'amount_lcy' => $line->amount_lcy,
                    'sort_order' => $i,
                ]);
            }

            // 4) Kassa hərəkətlərini geri al (bu transaction_number üzrə) və sil
            foreach (CashLedgerEntry::where('transaction_number', $txn)->get() as $cash) {
                $desk = CashDesk::whereKey($cash->cash_desk_code)->lockForUpdate()->first();
                if ($desk) {
                    $desk->balance_lcy = round((float) $desk->balance_lcy - $cash->entry_type->sign() * (float) $cash->amount_lcy, 2);
                    $desk->save();
                }
                $cash->delete();
            }

            // 5) Ledger yazılarını sil
            $ledger->lines()->delete();
            $ledger->delete();

            return $entry;
        });
    }

    /**
     * Post olunmuş ledger sətrinin məhsul detalını (çek) YERİNDƏ redaktə et.
     * Sətirləri əvəz edir, entry məbləğini cəmə görə yeniləyir və kassa FƏRQİNİ avtomatik düzəldir.
     * Yalnız gəlir/xərc (tək kassa yazısı) — pul konsistent qalır. Qaytarır: yenilənmiş ledger.
     *
     * @param array<int, array<string, mixed>> $lines
     */
    public function updateLedgerLines(FinanceLedgerEntry $ledger, array $lines): FinanceLedgerEntry
    {
        return DB::transaction(function () use ($ledger, $lines) {
            $ledger->lines()->delete();
            $total = 0.0;
            foreach (array_values($lines) as $line) {
                $item = Item::find($line['item_code']);
                $base = $item?->base_measure_code;
                $measureCode = $line['measure_code'] ?? null;
                $measWeight = isset($line['meas_weight']) ? (float) $line['meas_weight'] : null;
                if ($measureCode !== null && $item && $measureCode !== $base) {
                    $q = ItemMeasurement::where('item_code', $item->code)->where('measure_code', $measureCode);
                    if ($measWeight !== null) {
                        $q->where('meas_weight', $measWeight);
                    }
                    if (! $q->exists()) {
                        throw ValidationException::withMessages(['lines' => __('validation.exists', ['attribute' => 'measure_code'])]);
                    }
                } else {
                    $measureCode = $base;
                    $measWeight = null;
                }
                $amount = round((float) $line['qty'] * (float) $line['unit_price'], 2);
                $total += $amount;
                FinanceLedgerLine::create([
                    'ledger_entry_uid' => $ledger->uid,
                    'posting_date' => $ledger->posting_date->toDateString(),
                    'item_code' => $line['item_code'],
                    'item_name' => $item?->name,
                    'measure_code' => $measureCode,
                    'meas_weight' => $measWeight,
                    'qty' => $line['qty'],
                    'unit_price' => $line['unit_price'],
                    'amount_lcy' => $amount,
                ]);
            }

            // Sətirlər varsa → məbləğ = cəm, kassa fərqini düzəlt
            if (! empty($lines)) {
                $new = round($total, 2);
                $delta = round($new - (float) $ledger->amount_lcy, 2);
                if (abs($delta) >= 0.005) {
                    $cash = CashLedgerEntry::where('transaction_number', $ledger->transaction_number)->first();
                    if ($cash) {
                        $cash->amount_lcy = $new;
                        $cash->save();
                    }
                    $desk = CashDesk::whereKey($ledger->cash_desk_code)->lockForUpdate()->first();
                    if ($desk) {
                        $desk->balance_lcy = round((float) $desk->balance_lcy + $ledger->entry_type->cashDirection()->sign() * $delta, 2);
                        $desk->save();
                    }
                    $ledger->update(['amount_lcy' => $new]);
                }
            }

            return $ledger->fresh('lines');
        });
    }

    /** cash_ledger yazısı + hesab balansı. */
    private function cashMove(int $txn, string $date, string $docNo, string $deskCode, CashOrderType $direction, float $amount, ?string $descr, ?string $user): void
    {
        CashLedgerEntry::create([
            'transaction_number' => $txn,
            'posting_date' => $date,
            'doc_no' => $docNo,
            'cash_desk_code' => $deskCode,
            'amount_lcy' => $amount,
            'entry_type' => $direction->value,
            'descr' => $descr,
            'resp_person' => $user,
        ]);

        $desk = CashDesk::whereKey($deskCode)->lockForUpdate()->first();
        if ($desk) {
            $desk->balance_lcy = round((float) $desk->balance_lcy + $direction->sign() * $amount, 2);
            $desk->save();
        }
    }
}
