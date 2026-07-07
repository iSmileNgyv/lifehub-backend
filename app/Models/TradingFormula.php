<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class TradingFormula extends Model
{
    use HasUlids;

    protected $table = 'app.trading_formulas';

    protected $primaryKey = 'uid';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['uid', 'name', 'tiers', 'is_active'];

    public function uniqueIds(): array
    {
        return ['uid'];
    }

    protected function casts(): array
    {
        return [
            'tiers' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
