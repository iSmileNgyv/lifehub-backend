<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class VehicleReading extends Model
{
    use HasUlids;

    protected $table = 'app.vehicle_readings';

    protected $primaryKey = 'uid';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['uid', 'vehicle_uid', 'reading_date', 'km'];

    public function uniqueIds(): array
    {
        return ['uid'];
    }

    protected function casts(): array
    {
        return [
            'reading_date' => 'date',
            'km' => 'decimal:2',
        ];
    }
}
