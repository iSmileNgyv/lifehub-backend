<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Maliyyə jurnalı → post → finance_ledger (detal) + cash_ledger (pul).
     * finance_journal (başlıq, QALIR) → finance_journal_entry (draft, post-da SİLİNİR)
     * → finance_ledger_entry (posted detal: kateqoriya + amount) + cash_ledger_entry (təmiz pul).
     * Status sütunu yoxdur — ledger-də olmaq = post olunmuş deməkdir.
     */
    public function up(): void
    {
        DB::statement(<<<'SQL'
            DO $$ BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM pg_type t JOIN pg_namespace n ON n.oid = t.typnamespace
                    WHERE t.typname = 'finance_entry_type' AND n.nspname = 'app'
                ) THEN
                    CREATE TYPE app.finance_entry_type AS ENUM ('income', 'expense', 'transfer');
                END IF;
            END $$;
        SQL);

        // Jurnal başlığı (gündəlik) — QALIR
        Schema::create('app.finance_journal', function (Blueprint $table) {
            $table->string('code')->primary();          // FJ + ULID8
            $table->date('journal_date');
            $table->string('descr')->nullable();
            $table->string('resp_person')->nullable();
            $table->timestamps();
        });

        // İşlək sətir (draft) — post olanda SİLİNİR
        Schema::create('app.finance_journal_entry', function (Blueprint $table) {
            $table->ulid('uid')->primary();
            $table->string('jnl_code');
            $table->string('cash_desk_code');            // hansı hesab (nağd/kart)
            $table->string('category_code')->nullable(); // finance kateqoriyası (transferdə boş)
            $table->decimal('amount_lcy', 18, 2)->default(0);
            $table->string('descr')->nullable();
            $table->string('resp_person')->nullable();
            $table->timestamps();

            $table->index('jnl_code');
            $table->foreign('jnl_code')->references('code')->on('app.finance_journal')->cascadeOnDelete();
        });
        DB::statement("ALTER TABLE app.finance_journal_entry ADD COLUMN entry_type app.finance_entry_type NOT NULL DEFAULT 'expense'");

        // Posted detal ledger — QALIR (kateqoriya/amount hesabatının mənbəyi)
        Schema::create('app.finance_ledger_entry', function (Blueprint $table) {
            $table->string('uid')->primary();            // ULID
            $table->bigInteger('transaction_number');    // cash_ledger ilə link
            $table->date('posting_date');
            $table->string('jnl_code')->nullable();      // hansı jurnaldan (FK yox — jurnal qala/silinə bilər)
            $table->string('cash_desk_code');
            $table->string('category_code')->nullable();
            $table->decimal('amount_lcy', 18, 2)->default(0);
            $table->string('descr')->nullable();
            $table->string('resp_person')->nullable();
            $table->timestamps();

            $table->index('transaction_number');
            $table->index('posting_date');
            $table->index('category_code');
            $table->index('cash_desk_code');
        });
        DB::statement('ALTER TABLE app.finance_ledger_entry ADD COLUMN entry_type app.finance_entry_type NOT NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('app.finance_ledger_entry');
        Schema::dropIfExists('app.finance_journal_entry');
        Schema::dropIfExists('app.finance_journal');
        DB::statement('DROP TYPE IF EXISTS app.finance_entry_type');
    }
};
