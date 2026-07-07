<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TradingJournal extends Model
{
    protected $table = 'app.trading_journal';

    protected $primaryKey = 'code';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['code', 'cash_desk_code', 'descr', 'posting_date', 'posted_at', 'resp_person', 'status'];

    protected function casts(): array
    {
        return [
            'posting_date' => 'date',
            'posted_at' => 'datetime',
        ];
    }

    public function entries(): HasMany
    {
        return $this->hasMany(TradingJournalEntry::class, 'journal_code', 'code');
    }
}
