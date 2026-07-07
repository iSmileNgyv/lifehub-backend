<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class VehicleExpense extends Model
{
    use HasUlids;

    protected $table = 'app.vehicle_expenses';

    protected $primaryKey = 'uid';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['uid', 'vehicle_uid', 'date', 'title', 'amount', 'note'];

    public function uniqueIds(): array
    {
        return ['uid'];
    }

    protected function casts(): array
    {
        return ['date' => 'date', 'amount' => 'decimal:2'];
    }
}
