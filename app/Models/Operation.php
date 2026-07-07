<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Əməliyyat kataloqu (admin.operations). Kodla seed olunur, read-only.
 */
class Operation extends Model
{
    protected $table = 'admin.operations';

    protected $primaryKey = 'code';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['code', 'description', 'module', 'is_stock'];

    protected function casts(): array
    {
        return ['description' => 'array', 'is_stock' => 'boolean'];
    }
}

