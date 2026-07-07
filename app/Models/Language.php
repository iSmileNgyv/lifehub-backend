<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Data-tərcümə dili (app.languages reyestri).
 * Qeyd: App\Enums\Language (az/en/ru) UI lokalıdır; bu isə dinamik data dilləridir.
 */
class Language extends Model
{
    protected $table = 'admin.languages';

    protected $primaryKey = 'code';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['code', 'name', 'is_active', 'is_default', 'sort_order'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
