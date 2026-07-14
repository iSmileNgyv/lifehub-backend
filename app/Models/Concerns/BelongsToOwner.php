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
            if (empty($model->owner_uid) && ($u = auth()->user())) {
                $model->owner_uid = $u->uid;
            }
        });

        static::addGlobalScope('owner', function (Builder $builder) {
            if ($u = auth()->user()) {
                $builder->where($builder->getModel()->getTable().'.owner_uid', $u->uid);
            }
        });
    }
}
