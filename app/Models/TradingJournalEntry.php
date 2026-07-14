<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOwner;
use App\Enums\TradingEntryType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class TradingJournalEntry extends Model
{
    use BelongsToOwner;

    use HasUlids;

    protected $table = 'app.trading_journal_entry';

    protected $primaryKey = 'uid';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['uid', 'journal_code', 'entry_type', 'manat_amount', 'usd_qty', 'descr'];

    public function uniqueIds(): array
    {
        return ['uid'];
    }

    protected function casts(): array
    {
        return [
            'entry_type' => TradingEntryType::class,
            'manat_amount' => 'decimal:2',
            'usd_qty' => 'decimal:4',
        ];
    }
}
