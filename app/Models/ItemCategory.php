<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOwner;
use App\Enums\CategoryStatus;
use Illuminate\Database\Eloquent\Model;

class ItemCategory extends Model
{
    use BelongsToOwner;

    protected $table = 'app.item_categories';

    protected $primaryKey = 'code';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['code', 'parent_code', 'name', 'status', 'sort_order', 'in_use'];

    protected function casts(): array
    {
        return [
            'name' => 'array',
            'status' => CategoryStatus::class,
            'sort_order' => 'integer',
            'in_use' => 'boolean',
        ];
    }
}
