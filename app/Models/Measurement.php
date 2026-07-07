<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Measurement extends Model
{
    protected $table = 'app.measurements';

    protected $primaryKey = 'code';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['code', 'name', 'in_use'];

    protected function casts(): array
    {
        return [
            'name' => 'array',
            'in_use' => 'boolean',
        ];
    }
}
