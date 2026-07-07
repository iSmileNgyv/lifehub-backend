<?php

namespace App\Models;

use App\Enums\CashOrderType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class CashLedgerEntry extends Model
{
    use HasUlids;

    protected $table = 'app.cash_ledger_entry';

    protected $primaryKey = 'uid';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'uid', 'transaction_number', 'posting_date', 'doc_no',
        'cash_desk_code', 'amount_lcy', 'entry_type', 'descr', 'resp_person',
    ];

    public function uniqueIds(): array
    {
        return ['uid'];
    }

    protected function casts(): array
    {
        return [
            'entry_type' => CashOrderType::class,
            'posting_date' => 'date',
            'amount_lcy' => 'decimal:2',
        ];
    }
}
