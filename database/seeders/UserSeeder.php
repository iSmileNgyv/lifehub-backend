<?php

namespace Database\Seeders;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Demo istifadəçilər — infinite scroll / axtarış üçün test datası.
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            UserStatus::Active, UserStatus::Active, UserStatus::Active,
            UserStatus::Inactive, UserStatus::Banned,
        ];

        for ($i = 1; $i <= 45; $i++) {
            User::updateOrCreate(
                ['username' => "worker{$i}"],
                [
                    'name' => fake()->name(),
                    'password' => 'password',
                    'status' => $statuses[$i % count($statuses)],
                    'is_super_admin' => false,
                ],
            );
        }
    }
}
