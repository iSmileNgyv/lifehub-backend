<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class VehicleService extends Model
{
    use HasUlids;

    protected $table = 'app.vehicle_services';

    protected $primaryKey = 'uid';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'uid', 'vehicle_uid', 'item_code', 'item_name',
        'installed_date', 'installed_km', 'life_km', 'life_months', 'note', 'active', 'closed_at',
    ];

    public function uniqueIds(): array
    {
        return ['uid'];
    }

    protected function casts(): array
    {
        return [
            'item_name' => 'array',
            'installed_date' => 'date',
            'installed_km' => 'decimal:2',
            'life_km' => 'decimal:2',
            'life_months' => 'integer',
            'active' => 'boolean',
            'closed_at' => 'datetime',
        ];
    }
}
