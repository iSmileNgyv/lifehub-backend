<?php

namespace App\Enums;

enum CashOrderType: string
{
    case CashIn = 'cash_in';
    case CashOut = 'cash_out';

    /** Kassa balansına işarəli təsir (+1 giriş / −1 çıxış). */
    public function sign(): int
    {
        return $this === self::CashIn ? 1 : -1;
    }
}
