<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Maliyyə kateqoriyaları — gəlir/xərc təsnifatı (item_categories-dən AYRI, o məhsul üçündür).
     * Sonsuz alt-kateqoriya: adjacency list (parent_code self-FK). type = income/expense (kök rolu).
     */
    public function up(): void
    {
        DB::statement(<<<'SQL'
            DO $$ BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM pg_type t JOIN pg_namespace n ON n.oid = t.typnamespace
                    WHERE t.typname = 'finance_category_type' AND n.nspname = 'app'
                ) THEN
                    CREATE TYPE app.finance_category_type AS ENUM ('income', 'expense');
                END IF;
            END $$;
        SQL);

        Schema::create('app.finance_categories', function (Blueprint $table) {
            $table->string('code')->primary();
            $table->string('parent_code')->nullable();
            $table->jsonb('name');
            $table->integer('sort_order')->default(0);
            $table->boolean('in_use')->default(false);
            $table->timestamps();

            $table->index(['parent_code', 'sort_order']);
        });

        DB::statement('ALTER TABLE app.finance_categories ADD COLUMN type app.finance_category_type NOT NULL');
        DB::statement('ALTER TABLE app.finance_categories ADD CONSTRAINT finance_categories_parent_fk FOREIGN KEY (parent_code) REFERENCES app.finance_categories (code) ON DELETE RESTRICT');
    }

    public function down(): void
    {
        Schema::dropIfExists('app.finance_categories');
        DB::statement('DROP TYPE IF EXISTS app.finance_category_type');
    }
};
