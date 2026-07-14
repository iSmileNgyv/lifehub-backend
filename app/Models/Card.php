<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOwner;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class Card extends Model
{
    use BelongsToOwner;

    use HasUlids;

    protected $table = 'app.cards';

    protected $primaryKey = 'uid';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'uid', 'deck_uid', 'front', 'back', 'front_image', 'back_image', 'fields',
        'state', 'due', 'interval', 'ease', 'reps', 'lapses',
    ];

    public function uniqueIds(): array
    {
        return ['uid'];
    }

    protected function casts(): array
    {
        return [
            'due' => 'date',
            'interval' => 'integer',
            'ease' => 'decimal:2',
            'reps' => 'integer',
            'lapses' => 'integer',
            'fields' => 'array',
        ];
    }
}
