<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOwner;
use App\Enums\FinanceEntryType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Post olunmuş maliyyə sətri (detal) — kateqoriya/amount hesabatının mənbəyi. Qalır. */
class FinanceLedgerEntry extends Model
{
    use BelongsToOwner;

    use HasUlids;

    protected $table = 'app.finance_ledger_entry';

    protected $primaryKey = 'uid';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'uid', 'transaction_number', 'posting_date', 'jnl_code', 'entry_type',
        'cash_desk_code', 'category_code', 'amount_lcy', 'descr', 'resp_person',
    ];

    public function uniqueIds(): array
    {
        return ['uid'];
    }

    /** @return HasMany<FinanceLedgerLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(FinanceLedgerLine::class, 'ledger_entry_uid', 'uid');
    }

    protected function casts(): array
    {
        return [
            'entry_type' => FinanceEntryType::class,
            'posting_date' => 'date',
            'amount_lcy' => 'decimal:2',
        ];
    }
}
