<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOwner;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class VehicleFuel extends Model
{
    use BelongsToOwner;

    use HasUlids;

    protected $table = 'app.vehicle_fuel';

    protected $primaryKey = 'uid';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['uid', 'vehicle_uid', 'date', 'odometer_km', 'liters', 'amount', 'note'];

    public function uniqueIds(): array
    {
        return ['uid'];
    }

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'odometer_km' => 'decimal:2',
            'liters' => 'decimal:2',
            'amount' => 'decimal:2',
        ];
    }
}
