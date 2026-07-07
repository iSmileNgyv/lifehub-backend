<?php

namespace App\Models;

use App\Enums\Language;
use App\Enums\UserStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'username', 'password', 'status', 'is_super_admin', 'language'])]
#[Hidden(['password'])]
class User extends Authenticatable
{
    use HasApiTokens, HasUlids, Notifiable;

    protected $table = 'admin.users';

    /**
     * Açar: auto-increment id yox, ULID `uid`.
     */
    protected $primaryKey = 'uid';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * ULID hansı sütun(lar)a yazılsın.
     *
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['uid'];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'status' => UserStatus::class,
            'is_super_admin' => 'boolean',
            'language' => Language::class,
        ];
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            Role::class,
            'admin.user_role',
            'user_uid',
            'role_code',
            'uid',
            'code',
        );
    }

    /**
     * İstifadəçinin bu əməliyyata icazəsi varmı?
     * super_admin → həmişə. Əks halda hər hansı rolda access=1 (additive).
     */
    public function hasOperation(string $operationCode): bool
    {
        if ($this->is_super_admin) {
            return true;
        }

        return DB::table('admin.role_access as ra')
            ->join('admin.user_role as ur', 'ur.role_code', '=', 'ra.role_code')
            ->where('ur.user_uid', $this->uid)
            ->where('ra.operation_code', $operationCode)
            ->where('ra.access', true)
            ->exists();
    }

    /**
     * Effektiv icazə dəsti (operation_code-lar). super_admin → bütün kataloq.
     *
     * @return array<int, string>
     */
    public function permissionCodes(): array
    {
        if ($this->is_super_admin) {
            return Operation::query()->pluck('code')->all();
        }

        return DB::table('admin.role_access as ra')
            ->join('admin.user_role as ur', 'ur.role_code', '=', 'ra.role_code')
            ->where('ur.user_uid', $this->uid)
            ->where('ra.access', true)
            ->distinct()
            ->pluck('ra.operation_code')
            ->all();
    }
}
