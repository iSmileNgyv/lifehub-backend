<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Trading jurnalı (batch) — kassa JURNALDA təyin olunur (hər sətirdə yox).
     * Sətirlər: buy (USD alışı) / sell (manat→formula USD). Post gündə 1 dəfə.
     * status: draft → posted (post-dan sonra kilid; sətirlər qalır, ledger yaranır).
     */
    public function up(): void
    {
        DB::statement(<<<'SQL'
            DO $$ BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM pg_type t JOIN pg_namespace n ON n.oid = t.typnamespace
                    WHERE t.typname = 'trading_journal_status' AND n.nspname = 'app'
                ) THEN
                    CREATE TYPE app.trading_journal_status AS ENUM ('draft', 'posted');
                END IF;
            END $$;
        SQL);

        Schema::create('app.trading_journal', function (Blueprint $table) {
            $table->string('code')->primary();          // TJ_0001
            $table->string('cash_desk_code')->nullable();
            $table->string('descr')->nullable();
            $table->date('posting_date');
            $table->timestamp('posted_at')->nullable();
            $table->string('resp_person')->nullable();
            $table->timestamps();

            $table->index('cash_desk_code');
        });
        DB::statement("ALTER TABLE app.trading_journal ADD COLUMN status app.trading_journal_status NOT NULL DEFAULT 'draft'");

        Schema::create('app.trading_journal_entry', function (Blueprint $table) {
            $table->string('uid')->primary();           // ULID
            $table->string('journal_code');
            $table->decimal('manat_amount', 18, 2);     // buy: ödənilən / sell: alınan
            $table->decimal('usd_qty', 18, 4);          // buy: daxil / sell: formuladan
            $table->string('descr')->nullable();
            $table->timestamps();

            $table->foreign('journal_code')->references('code')->on('app.trading_journal')->cascadeOnDelete();
            $table->index('journal_code');
        });
        DB::statement('ALTER TABLE app.trading_journal_entry ADD COLUMN entry_type app.trading_entry_type NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('app.trading_journal_entry');
        Schema::dropIfExists('app.trading_journal');
        DB::statement('DROP TYPE IF EXISTS app.trading_journal_status');
    }
};
