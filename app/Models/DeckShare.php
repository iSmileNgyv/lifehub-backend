<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Koloda paylaşım kodu. Owner-scope YOXDUR — çünki idxal (import) kod üzrə
 * cross-tenant axtarış tələb edir. Sahiblik yoxlaması controller-də manual edilir.
 */
class DeckShare extends Model
{
    protected $table = 'app.deck_shares';

    protected $primaryKey = 'code';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['code', 'deck_uid', 'owner_uid', 'revoked_at'];

    protected function casts(): array
    {
        return ['revoked_at' => 'datetime'];
    }

    public function deck(): BelongsTo
    {
        return $this->belongsTo(Deck::class, 'deck_uid', 'uid');
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null;
    }
}
