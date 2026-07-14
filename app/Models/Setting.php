<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Qlobal sistem ayarı (key/value) — tenant deyil. */
class Setting extends Model
{
    protected $table = 'admin.settings';

    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['key', 'value'];

    public static function getBool(string $key, bool $default = false): bool
    {
        $row = static::find($key);

        return $row === null ? $default : filter_var($row->value, FILTER_VALIDATE_BOOLEAN);
    }

    public static function put(string $key, string $value): void
    {
        static::updateOrCreate(['key' => $key], ['value' => $value]);
    }
}
