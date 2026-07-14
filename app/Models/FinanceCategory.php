<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOwner;
use App\Enums\FinanceCategoryType;
use Illuminate\Database\Eloquent\Model;

class FinanceCategory extends Model
{
    use BelongsToOwner;

    protected $table = 'app.finance_categories';

    protected $primaryKey = 'code';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['code', 'parent_code', 'name', 'type', 'sort_order', 'in_use'];

    protected function casts(): array
    {
        return [
            'name' => 'array',
            'type' => FinanceCategoryType::class,
            'sort_order' => 'integer',
            'in_use' => 'boolean',
        ];
    }
}
