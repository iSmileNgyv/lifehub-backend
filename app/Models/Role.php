<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    protected $table = 'admin.roles';

    protected $primaryKey = 'code';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = ['code', 'name'];

    /**
     * Bu rolun icazəli olduğu (access=1) əməliyyatlar.
     */
    public function operations(): BelongsToMany
    {
        return $this->belongsToMany(
            Operation::class,
            'admin.role_access',
            'role_code',
            'operation_code',
            'code',
            'code',
        )->withPivot('access')->withTimestamps();
    }
}
