<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * Multi-tenant izolyasiya: hər yazı bir istifadəçiyə (owner_uid) aiddir.
 * - Global scope: hər sorğu avtomatik cari user-in datası ilə filtrlənir (unutma riski yox).
 * - Auto-fill: yeni yazı yaradılanda owner_uid avtomatik cari user-dən qoyulur.
 * Auth yoxdursa (seeder/console/migration) scope tətbiq olunmur — bütün data görünür.
 */
trait BelongsToOwner
{
    protected static function bootBelongsToOwner(): void
    {
        static::creating(function ($model) {
            if (empty($model->owner_uid) && ($uid = static::currentOwnerUid())) {
                $model->owner_uid = $uid;
            }
        });

        static::addGlobalScope('owner', function (Builder $builder) {
            if ($uid = static::currentOwnerUid()) {
                $builder->where($builder->getModel()->getTable().'.owner_uid', $uid);
            }
        });
    }

    /**
     * Cari authenticated user-in uid-i.
     * API sanctum guard-ını, sonra default (web) guard-ı yoxlayır (default guard=web olduğu üçün
     * auth()->user() API sorğusunda null qaytarır — ona görə açıq şəkildə sanctum yoxlanır).
     * Auth yoxdursa (console/seeder) null.
     */
    protected static function currentOwnerUid(): ?string
    {
        return auth('sanctum')->user()?->uid ?? auth()->user()?->uid;
    }
}
