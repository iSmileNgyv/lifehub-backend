<?php

namespace App\Models;

use App\Enums\ItemStatus;
use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    protected $table = 'app.items';

    protected $primaryKey = 'code';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['code', 'name', 'category_code', 'base_measure_code', 'image', 'in_use', 'status'];

    protected function casts(): array
    {
        return [
            'name' => 'array',
            'status' => ItemStatus::class,
            'in_use' => 'boolean',
        ];
    }
}
