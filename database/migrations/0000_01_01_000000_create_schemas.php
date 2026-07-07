<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Sxemlər:
     *  - admin : sistem / auth (users, roles, user_role, role_access, operations)
     *  - app   : biznes (anbar, satış, partnyor, hesablar və s.)
     * Framework cədvəlləri (migrations, cache, jobs, sessions) public-də qalır.
     */
    public function up(): void
    {
        DB::statement('CREATE SCHEMA IF NOT EXISTS admin');
        DB::statement('CREATE SCHEMA IF NOT EXISTS app');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement('DROP SCHEMA IF EXISTS app CASCADE');
        DB::statement('DROP SCHEMA IF EXISTS admin CASCADE');
    }
};
