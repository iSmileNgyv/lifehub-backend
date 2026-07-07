<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kart şablonları (formullar) — flashcard-lar üçün struktur schema.
     * fields = [{key,label,description,type,side,section}] → dinamik forma + görünüş + AI.
     * Deck bir şablon seçir; şablonlu kartlar dəyərləri `cards.fields`-də saxlayır.
     */
    public function up(): void
    {
        Schema::create('app.card_templates', function (Blueprint $table) {
            $table->string('uid')->primary();
            $table->string('owner_uid');
            $table->string('name');
            $table->string('description')->nullable();
            $table->jsonb('fields')->nullable();
            $table->timestamps();

            $table->index('owner_uid');
        });

        Schema::table('app.decks', function (Blueprint $table) {
            $table->string('template_uid')->nullable()->after('description');
            $table->foreign('template_uid')->references('uid')->on('app.card_templates')->nullOnDelete();
        });

        Schema::table('app.cards', function (Blueprint $table) {
            $table->jsonb('fields')->nullable()->after('back_image');
        });

        // Şablonlu kartlarda front/back istifadə olunmur → nullable
        DB::statement('ALTER TABLE app.cards ALTER COLUMN front DROP NOT NULL');
        DB::statement('ALTER TABLE app.cards ALTER COLUMN back DROP NOT NULL');
    }

    public function down(): void
    {
        Schema::table('app.decks', function (Blueprint $table) {
            $table->dropForeign(['template_uid']);
            $table->dropColumn('template_uid');
        });
        Schema::table('app.cards', function (Blueprint $table) {
            $table->dropColumn('fields');
        });
        Schema::dropIfExists('app.card_templates');
    }
};
