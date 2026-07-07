<?php

namespace App\Models;

use App\Enums\CashDeskStatus;
use Illuminate\Database\Eloquent\Model;

class CashDesk extends Model
{
    protected $table = 'app.cash_desk';

    protected $primaryKey = 'code';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['code', 'description', 'address', 'resp_person', 'balance_lcy', 'status', 'in_use'];

    protected function casts(): array
    {
        return [
            'description' => 'array',
            'balance_lcy' => 'decimal:2',
            'status' => CashDeskStatus::class,
            'in_use' => 'boolean',
        ];
    }
}
