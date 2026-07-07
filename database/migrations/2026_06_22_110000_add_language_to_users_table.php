<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Native enum (idempotent — fresh migrate üçün)
        DB::statement(<<<'SQL'
            DO $$ BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM pg_type t
                    JOIN pg_namespace n ON n.oid = t.typnamespace
                    WHERE t.typname = 'user_language' AND n.nspname = 'admin'
                ) THEN
                    CREATE TYPE admin.user_language AS ENUM ('az', 'en', 'ru');
                END IF;
            END $$;
        SQL);

        DB::statement("ALTER TABLE admin.users ADD COLUMN language admin.user_language NOT NULL DEFAULT 'az'");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE admin.users DROP COLUMN IF EXISTS language');
        DB::statement('DROP TYPE IF EXISTS admin.user_language');
    }
};
