<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Kassa kitabçası — post-lu pul hərəkəti. entry_type = cash_in/cash_out. */
    public function up(): void
    {
        DB::statement(<<<'SQL'
            DO $$ BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM pg_type t JOIN pg_namespace n ON n.oid = t.typnamespace
                    WHERE t.typname = 'cash_order_type' AND n.nspname = 'app'
                ) THEN
                    CREATE TYPE app.cash_order_type AS ENUM ('cash_in', 'cash_out');
                END IF;
            END $$;
        SQL);

        Schema::create('app.cash_ledger_entry', function (Blueprint $table) {
            $table->string('uid')->primary();
            $table->bigInteger('transaction_number');
            $table->date('posting_date');
            $table->string('doc_no')->nullable();
            $table->string('cash_desk_code');
            $table->decimal('amount_lcy', 18, 2)->default(0);
            $table->string('descr')->nullable();
            $table->string('resp_person')->nullable();
            $table->timestamps();

            $table->index('cash_desk_code');
            $table->index('transaction_number');
        });

        DB::statement('ALTER TABLE app.cash_ledger_entry ADD COLUMN entry_type app.cash_order_type NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('app.cash_ledger_entry');
        DB::statement('DROP TYPE IF EXISTS app.cash_order_type');
    }
};
