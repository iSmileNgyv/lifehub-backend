<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOwner;
use Illuminate\Database\Eloquent\Model;

class Measurement extends Model
{
    use BelongsToOwner;

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
