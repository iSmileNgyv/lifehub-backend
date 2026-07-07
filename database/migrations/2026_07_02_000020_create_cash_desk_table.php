<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Kassalar (cash desk). code manual. balance_lcy keşlənmiş qalıq (ledger idarə edir). */
    public function up(): void
    {
        DB::statement(<<<'SQL'
            DO $$ BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM pg_type t JOIN pg_namespace n ON n.oid = t.typnamespace
                    WHERE t.typname = 'cash_desk_status' AND n.nspname = 'app'
                ) THEN
                    CREATE TYPE app.cash_desk_status AS ENUM ('ACTIVE', 'BLOCKED');
                END IF;
            END $$;
        SQL);

        Schema::create('app.cash_desk', function (Blueprint $table) {
            $table->string('code')->primary();
            $table->jsonb('description')->nullable();
            $table->string('address')->nullable();
            $table->string('resp_person')->nullable();
            $table->decimal('balance_lcy', 18, 2)->default(0);
            $table->boolean('in_use')->default(false);
            $table->timestamps();
        });

        DB::statement("ALTER TABLE app.cash_desk ADD COLUMN status app.cash_desk_status NOT NULL DEFAULT 'ACTIVE'");
    }

    public function down(): void
    {
        Schema::dropIfExists('app.cash_desk');
        DB::statement('DROP TYPE IF EXISTS app.cash_desk_status');
    }
};
