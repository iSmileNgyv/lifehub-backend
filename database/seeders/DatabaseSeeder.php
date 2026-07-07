<?php

namespace Database\Seeders;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(OperationSeeder::class);
        $this->call(LanguageSeeder::class);
        $this->call(NumberSeriesSeeder::class);

        // Self-register yoxdur — başlanğıc super-admin istifadəçisi.
        User::updateOrCreate(
            ['username' => 'admin'],
            [
                'name' => 'Administrator',
                'password' => 'password',
                'status' => UserStatus::Active,
                'is_super_admin' => true,
            ],
        );
    }
}
