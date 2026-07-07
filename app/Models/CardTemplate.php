<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class CardTemplate extends Model
{
    use HasUlids;

    protected $table = 'app.card_templates';

    protected $primaryKey = 'uid';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['uid', 'owner_uid', 'name', 'description', 'ai_instruction', 'fields'];

    public function uniqueIds(): array
    {
        return ['uid'];
    }

    protected function casts(): array
    {
        return ['fields' => 'array'];
    }
}
