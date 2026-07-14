<?php

namespace App\Enums;

use App\Enums\CashOrderType;

/** Maliyyə jurnal/ledger sətrinin növü. */
enum FinanceEntryType: string
{
    case Income = 'income';
    case Expense = 'expense';
    case Transfer = 'transfer';

    /** Bu tip kassaya necə təsir edir (gəlir=giriş, xərc=çıxış). Transfer ayrıca işlənir. */
    public function cashDirection(): CashOrderType
    {
        return $this === self::Income ? CashOrderType::CashIn : CashOrderType::CashOut;
    }
}
