<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOwner;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class CardTemplate extends Model
{
    use BelongsToOwner;

    use HasUlids;

    protected $table = 'app.card_templates';

    protected $primaryKey = 'uid';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['uid', 'owner_uid', 'name', 'description', 'ai_instruction', 'fields', 'display'];

    public function uniqueIds(): array
    {
        return ['uid'];
    }

    protected function casts(): array
    {
        return ['fields' => 'array', 'display' => 'array'];
    }
}
