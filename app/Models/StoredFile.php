<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class StoredFile extends Model
{
    use HasUlids;

    protected $table = 'app.stored_files';

    protected $primaryKey = 'uid';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['uid', 'driver', 'path', 'original_name', 'mime', 'size'];

    public function uniqueIds(): array
    {
        return ['uid'];
    }
}
