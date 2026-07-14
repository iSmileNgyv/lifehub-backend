<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOwner;
use App\Enums\FinanceEntryType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Maliyyə jurnalının işlək sətri (draft) — post olanda silinir. */
class FinanceJournalEntry extends Model
{
    use BelongsToOwner;

    use HasUlids;

    protected $table = 'app.finance_journal_entry';

    protected $primaryKey = 'uid';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'uid', 'jnl_code', 'posting_date', 'entry_type', 'cash_desk_code', 'to_cash_desk_code',
        'category_code', 'amount_lcy', 'descr', 'resp_person',
    ];

    public function uniqueIds(): array
    {
        return ['uid'];
    }

    /** @return HasMany<FinanceJournalLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(FinanceJournalLine::class, 'entry_uid', 'uid');
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
