<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOwner;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/** Maliyyə entry-sinin məhsul sətri (draft çek) — post-da silinir. */
class FinanceJournalLine extends Model
{
    use BelongsToOwner;

    use HasUlids;

    protected $table = 'app.finance_journal_line';

    protected $primaryKey = 'uid';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'uid', 'entry_uid', 'item_code', 'item_name', 'measure_code',
        'qty', 'unit_price', 'amount_lcy', 'sort_order',
    ];

    public function uniqueIds(): array
    {
        return ['uid'];
    }

    protected function casts(): array
    {
        return [
            'item_name' => 'array',
            'qty' => 'decimal:4',
            'unit_price' => 'decimal:2',
            'amount_lcy' => 'decimal:2',
            'sort_order' => 'integer',
        ];
    }
}
