<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Öyrənmə modulu (Anki tipli flashcard + SM-2 aralıqlı təkrar).
     * decks = kolodalar (istifadəçiyə aid). cards = ön/arxa (+şəkil) + SRS vəziyyəti.
     */
    public function up(): void
    {
        Schema::create('app.decks', function (Blueprint $table) {
            $table->string('uid')->primary();
            $table->string('owner_uid');            // hansı istifadəçi
            $table->string('name');
            $table->string('description')->nullable();
            $table->timestamps();

            $table->index('owner_uid');
        });

        DB::statement(<<<'SQL'
            DO $$ BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM pg_type t JOIN pg_namespace n ON n.oid = t.typnamespace
                    WHERE t.typname = 'card_state' AND n.nspname = 'app'
                ) THEN
                    CREATE TYPE app.card_state AS ENUM ('new', 'learning', 'review');
                END IF;
            END $$;
        SQL);

        Schema::create('app.cards', function (Blueprint $table) {
            $table->string('uid')->primary();
            $table->string('deck_uid');
            $table->text('front');                  // sual (uzun ola bilər)
            $table->text('back');                   // cavab (uzun — feillər və s.)
            $table->string('front_image')->nullable();
            $table->string('back_image')->nullable();
            // SRS (SM-2)
            $table->date('due');
            $table->integer('interval')->default(0);        // gün
            $table->decimal('ease', 4, 2)->default(2.50);   // asanlıq əmsalı
            $table->integer('reps')->default(0);
            $table->integer('lapses')->default(0);
            $table->timestamps();

            $table->foreign('deck_uid')->references('uid')->on('app.decks')->cascadeOnDelete();
            $table->index(['deck_uid', 'due']);
        });

        DB::statement("ALTER TABLE app.cards ADD COLUMN state app.card_state NOT NULL DEFAULT 'new'");
    }

    public function down(): void
    {
        Schema::dropIfExists('app.cards');
        Schema::dropIfExists('app.decks');
        DB::statement('DROP TYPE IF EXISTS app.card_state');
    }
};
