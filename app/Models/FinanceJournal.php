<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOwner;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Maliyyə jurnalı başlığı (gündəlik). Post-dan sonra qalır (içi boşalır). */
class FinanceJournal extends Model
{
    use BelongsToOwner;

    protected $table = 'app.finance_journal';

    protected $primaryKey = 'code';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['code', 'journal_date', 'descr', 'resp_person'];

    protected function casts(): array
    {
        return [
            'journal_date' => 'date',
        ];
    }

    /** @return HasMany<FinanceJournalEntry, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(FinanceJournalEntry::class, 'jnl_code', 'code');
    }
}
