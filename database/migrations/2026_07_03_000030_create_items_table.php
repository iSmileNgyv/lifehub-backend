<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Items — LifeHub-da ehtiyat hissələr / sərf materialları (yağ, filtr, remen...). */
    public function up(): void
    {
        DB::statement(<<<'SQL'
            DO $$ BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM pg_type t JOIN pg_namespace n ON n.oid = t.typnamespace
                    WHERE t.typname = 'item_status' AND n.nspname = 'app'
                ) THEN
                    CREATE TYPE app.item_status AS ENUM ('ACTIVE', 'BLOCKED');
                END IF;
            END $$;
        SQL);

        Schema::create('app.items', function (Blueprint $table) {
            $table->string('code')->primary();
            $table->jsonb('name');
            $table->string('category_code')->nullable();
            $table->string('base_measure_code');
            $table->string('image')->nullable();
            $table->boolean('in_use')->default(false);
            $table->timestamps();

            $table->foreign('category_code')->references('code')->on('app.item_categories')->restrictOnDelete();
            $table->foreign('base_measure_code')->references('code')->on('app.measurements')->restrictOnDelete();
            $table->index('category_code');
        });

        DB::statement("ALTER TABLE app.items ADD COLUMN status app.item_status NOT NULL DEFAULT 'ACTIVE'");
    }

    public function down(): void
    {
        Schema::dropIfExists('app.items');
        DB::statement('DROP TYPE IF EXISTS app.item_status');
    }
};
