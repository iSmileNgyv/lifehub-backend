<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Owner üzrə Telegram bot davranışı (study push konfiqurasiyası). owner_uid = user uid. */
class TelegramSetting extends Model
{
    protected $table = 'admin.telegram_settings';

    protected $primaryKey = 'owner_uid';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'owner_uid', 'study_enabled', 'study_deck_uid', 'interval_min',
        'active_from', 'active_to', 'cards_per_push', 'last_pushed_at',
        'mode', 'ext_enabled', 'ext_rotate_sec', 'ext_notify_min', 'ext_notify',
    ];

    protected function casts(): array
    {
        return [
            'study_enabled' => 'boolean',
            'interval_min' => 'integer',
            'cards_per_push' => 'integer',
            'last_pushed_at' => 'datetime',
            'ext_enabled' => 'boolean',
            'ext_rotate_sec' => 'integer',
            'ext_notify_min' => 'integer',
            'ext_notify' => 'boolean',
        ];
    }
}
