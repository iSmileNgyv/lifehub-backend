<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Sonsuz alt-kateqoriya: adjacency list (parent_code self-FK). */
    public function up(): void
    {
        DB::statement(<<<'SQL'
            DO $$ BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM pg_type t JOIN pg_namespace n ON n.oid = t.typnamespace
                    WHERE t.typname = 'category_status' AND n.nspname = 'app'
                ) THEN
                    CREATE TYPE app.category_status AS ENUM ('ACTIVE', 'BLOCKED');
                END IF;
            END $$;
        SQL);

        Schema::create('app.item_categories', function (Blueprint $table) {
            $table->string('code')->primary();
            $table->string('parent_code')->nullable();
            $table->jsonb('name');
            $table->integer('sort_order')->default(0);
            $table->boolean('in_use')->default(false);
            $table->timestamps();

            $table->index(['parent_code', 'sort_order']);
        });

        DB::statement("ALTER TABLE app.item_categories ADD COLUMN status app.category_status NOT NULL DEFAULT 'ACTIVE'");
        DB::statement('ALTER TABLE app.item_categories ADD CONSTRAINT item_categories_parent_fk FOREIGN KEY (parent_code) REFERENCES app.item_categories (code) ON DELETE RESTRICT');
    }

    public function down(): void
    {
        Schema::dropIfExists('app.item_categories');
        DB::statement('DROP TYPE IF EXISTS app.category_status');
    }
};
