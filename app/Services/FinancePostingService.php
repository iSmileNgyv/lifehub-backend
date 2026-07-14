<?php

namespace App\Services;

use App\Enums\CashOrderType;
use App\Enums\FinanceEntryType;
use App\Models\CashDesk;
use App\Models\CashLedgerEntry;
use App\Models\FinanceJournal;
use App\Models\FinanceJournalEntry;
use App\Models\FinanceLedgerEntry;
use App\Models\FinanceLedgerLine;
use App\Support\TransactionNumber;
use Illuminate\Support\Facades\DB;
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
                'qty' => $line->qty,
                'unit_price' => $line->unit_price,
                'amount_lcy' => $line->amount_lcy,
            ]);
        }

        $this->cashMove($txn, $date, $journal->code, $entry->cash_desk_code, $type->cashDirection(), $amount, $entry->descr, $user);
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
