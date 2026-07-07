<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class ItemMeasurement extends Model
{
    use HasUlids;

    protected $table = 'app.items_measurement';

    protected $primaryKey = 'uid';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['uid', 'item_code', 'base_measure_code', 'measure_code', 'meas_weight', 'in_use'];

    public function uniqueIds(): array
    {
        return ['uid'];
    }

    protected function casts(): array
    {
        return [
            'meas_weight' => 'decimal:4',
            'in_use' => 'boolean',
        ];
    }
}
