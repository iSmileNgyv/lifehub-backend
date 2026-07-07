<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Transaction nömrəsi generatoru — illik sıfırlanan, atomik.
 * Nömrə = year * 1_000_000_000 + last_no (bigint, YYYY+9rəqəm). Bir ildə 1 milyard yer.
 * Bir post sətrində ledger + kassa eyni nömrəni paylaşır (link).
 */
class TransactionNumber
{
    public static function next(): int
    {
        $year = (int) now()->format('Y');

        DB::table('app.transaction_seq')->insertOrIgnore(['year' => $year, 'last_no' => 0]);

        $row = DB::table('app.transaction_seq')->where('year', $year)->lockForUpdate()->first();
        $next = ((int) $row->last_no) + 1;
        DB::table('app.transaction_seq')->where('year', $year)->update(['last_no' => $next]);

        return $year * 1_000_000_000 + $next;
    }
}
