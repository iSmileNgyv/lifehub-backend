<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOwner;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/** Post olunmuş məhsul sətri — məhsul-səviyyə hesabatın mənbəyi. Qalır. */
class FinanceLedgerLine extends Model
{
    use BelongsToOwner;

    use HasUlids;

    protected $table = 'app.finance_ledger_line';

    protected $primaryKey = 'uid';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'uid', 'ledger_entry_uid', 'posting_date', 'item_code', 'item_name',
        'measure_code', 'meas_weight', 'qty', 'unit_price', 'amount_lcy',
    ];

    public function uniqueIds(): array
    {
        return ['uid'];
    }

    protected function casts(): array
    {
        return [
            'posting_date' => 'date',
            'item_name' => 'array',
            'meas_weight' => 'decimal:4',
            'qty' => 'decimal:4',
            'unit_price' => 'decimal:2',
            'amount_lcy' => 'decimal:2',
        ];
    }
}
