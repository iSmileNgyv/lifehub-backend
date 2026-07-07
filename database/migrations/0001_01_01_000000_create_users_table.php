<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Native Postgres enum: istifadəçi statusu (idempotent — fresh migrate üçün)
        DB::statement(<<<'SQL'
            DO $$ BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM pg_type t
                    JOIN pg_namespace n ON n.oid = t.typnamespace
                    WHERE t.typname = 'user_status' AND n.nspname = 'admin'
                ) THEN
                    CREATE TYPE admin.user_status AS ENUM ('active', 'inactive', 'banned');
                END IF;
            END $$;
        SQL);

        Schema::create('admin.users', function (Blueprint $table) {
            $table->ulid('uid')->primary();
            $table->string('name');
            $table->string('username')->unique();
            $table->string('password');
            $table->boolean('is_super_admin')->default(false);
            $table->timestamps();
        });

        // status sütunu native enum tipi ilə
        DB::statement("ALTER TABLE admin.users ADD COLUMN status admin.user_status NOT NULL DEFAULT 'active'");

        // Web sessiya cədvəli (framework) — public-də qalır
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->ulid('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('admin.users');
        DB::statement('DROP TYPE IF EXISTS admin.user_status');
    }
};
