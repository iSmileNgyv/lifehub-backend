<?php

namespace App\Models;

use App\Enums\TradingEntryType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * Trading kitabçası — Procurement item_ledger_entry güzgüsü, amma item YOX (tək USD balansı).
 * Buy = FIFO təbəqə (positive, remain/open). Sell = FIFO çıxış (COGS manatla).
 * qty = USD, unit_amount_lcy = manat/USD, amount_lcy = manat.
 */
class TradingLedgerEntry extends Model
{
    use HasUlids;

    protected $table = 'app.trading_ledger_entry';

    protected $primaryKey = 'uid';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'uid', 'transaction_number', 'posting_date', 'doc_no', 'journal_code',
        'entry_type', 'initial_qty', 'remain_qty', 'positive', 'open',
        'unit_amount_lcy', 'amount_lcy', 'resp_person',
    ];

    public function uniqueIds(): array
    {
        return ['uid'];
    }

    protected function casts(): array
    {
        return [
            'entry_type' => TradingEntryType::class,
            'posting_date' => 'date',
            'initial_qty' => 'decimal:4',
            'remain_qty' => 'decimal:4',
            'positive' => 'boolean',
            'open' => 'boolean',
            'unit_amount_lcy' => 'decimal:4',
            'amount_lcy' => 'decimal:2',
        ];
    }
}
