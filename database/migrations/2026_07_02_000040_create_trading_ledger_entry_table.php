<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Trading kitabçası — item_ledger_entry güzgüsü, item YOX (tək USD balansı).
     * buy = FIFO təbəqə (positive, remain/open). sell = FIFO çıxış.
     */
    public function up(): void
    {
        DB::statement(<<<'SQL'
            DO $$ BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM pg_type t JOIN pg_namespace n ON n.oid = t.typnamespace
                    WHERE t.typname = 'trading_entry_type' AND n.nspname = 'app'
                ) THEN
                    CREATE TYPE app.trading_entry_type AS ENUM ('buy', 'sell');
                END IF;
            END $$;
        SQL);

        Schema::create('app.trading_ledger_entry', function (Blueprint $table) {
            $table->string('uid')->primary();              // ULID
            $table->bigInteger('transaction_number');
            $table->date('posting_date');
            $table->string('doc_no')->nullable();
            $table->string('journal_code')->nullable();    // hansı jurnaldan
            $table->decimal('initial_qty', 18, 4)->default(0); // USD
            $table->decimal('remain_qty', 18, 4)->default(0);  // FIFO qalıq (USD)
            $table->boolean('positive');                   // giriş(+) buy / çıxış(−) sell
            $table->boolean('open')->default(false);       // remain_qty>0 (yeyilə bilər)
            $table->decimal('unit_amount_lcy', 18, 4)->default(0); // manat / USD
            $table->decimal('amount_lcy', 18, 2)->default(0);      // manat
            $table->string('resp_person')->nullable();
            $table->timestamps();

            $table->index(['open']);
            $table->index('transaction_number');
            $table->index('journal_code');
        });

        DB::statement('ALTER TABLE app.trading_ledger_entry ADD COLUMN entry_type app.trading_entry_type NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('app.trading_ledger_entry');
        DB::statement('DROP TYPE IF EXISTS app.trading_entry_type');
    }
};
