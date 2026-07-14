<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Maliyyə sətrinin məhsul detalı (çek) — opsional. item kataloqundan.
     * finance_journal_line (draft, entry-yə bağlı) → post → finance_ledger_line (qalır).
     * Sətirlərin cəmi = entry məbləği (server hesablayır).
     */
    public function up(): void
    {
        Schema::create('app.finance_journal_line', function (Blueprint $table) {
            $table->ulid('uid')->primary();
            $table->string('entry_uid');
            $table->string('item_code');
            $table->jsonb('item_name')->nullable();     // snapshot
            $table->string('measure_code')->nullable();
            $table->decimal('qty', 18, 4)->default(0);
            $table->decimal('unit_price', 18, 2)->default(0);
            $table->decimal('amount_lcy', 18, 2)->default(0); // qty × unit_price
            $table->integer('sort_order')->default(0);
            $table->timestamps();

            $table->index('entry_uid');
            $table->foreign('entry_uid')->references('uid')->on('app.finance_journal_entry')->cascadeOnDelete();
        });

        Schema::create('app.finance_ledger_line', function (Blueprint $table) {
            $table->ulid('uid')->primary();
            $table->string('ledger_entry_uid');          // FK yox — ledger qalır
            $table->date('posting_date');
            $table->string('item_code');
            $table->jsonb('item_name')->nullable();
            $table->string('measure_code')->nullable();
            $table->decimal('qty', 18, 4)->default(0);
            $table->decimal('unit_price', 18, 2)->default(0);
            $table->decimal('amount_lcy', 18, 2)->default(0);
            $table->timestamps();

            $table->index('ledger_entry_uid');
            $table->index('item_code');
            $table->index('posting_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app.finance_ledger_line');
        Schema::dropIfExists('app.finance_journal_line');
    }
};
