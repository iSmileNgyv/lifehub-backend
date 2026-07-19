<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOwner;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Deck extends Model
{
    use BelongsToOwner;

    use HasUlids;

    protected $table = 'app.decks';

    protected $primaryKey = 'uid';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['uid', 'owner_uid', 'name', 'description', 'template_uid', 'source_deck_uid', 'source_share_code'];

    public function uniqueIds(): array
    {
        return ['uid'];
    }

    public function cards(): HasMany
    {
        return $this->hasMany(Card::class, 'deck_uid', 'uid');
    }
}
