<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOwner;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/** Aylıq büdcə/hədəf (kateqoriya limiti / ümumi xərc tavanı / gəlir hədəfi). */
class FinanceBudget extends Model
{
    use BelongsToOwner;

    use HasUlids;

    protected $table = 'app.finance_budgets';

    protected $primaryKey = 'uid';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['uid', 'owner_uid', 'kind', 'category_code', 'amount_lcy'];

    public function uniqueIds(): array
    {
        return ['uid'];
    }

    protected function casts(): array
    {
        return ['amount_lcy' => 'decimal:2'];
    }
}
