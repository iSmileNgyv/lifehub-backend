<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Vehicle extends Model
{
    use HasUlids;

    protected $table = 'app.vehicles';

    protected $primaryKey = 'uid';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['uid', 'name', 'plate', 'unit', 'avg_km_per_day', 'note'];

    public function uniqueIds(): array
    {
        return ['uid'];
    }

    protected function casts(): array
    {
        return ['avg_km_per_day' => 'decimal:2'];
    }

    public function readings(): HasMany
    {
        return $this->hasMany(VehicleReading::class, 'vehicle_uid', 'uid');
    }

    public function services(): HasMany
    {
        return $this->hasMany(VehicleService::class, 'vehicle_uid', 'uid');
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(VehicleExpense::class, 'vehicle_uid', 'uid');
    }

    public function fuel(): HasMany
    {
        return $this->hasMany(VehicleFuel::class, 'vehicle_uid', 'uid');
    }
}
