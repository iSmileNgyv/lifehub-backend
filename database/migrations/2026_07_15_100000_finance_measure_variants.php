<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Ölçü variantları: eyni məhsulda eyni vahid (məs. "ədəd") fərqli çəkilərlə ola bilər
     * (5 litrlik qab vs 8 litrlik qab — hər ikisi "ədəd"). Bunun üçün items_measurement-dəki
     * (item_code, measure_code) unique götürülür. Çek/ledger sətrinə meas_weight snapshot əlavə
     * edilir ki hansı variant olduğu (və qiymət tarixçəsi) itməsin. NULL = baza vahidi (×1).
     */
    public function up(): void
    {
        Schema::table('app.items_measurement', function (Blueprint $table) {
            $table->dropUnique(['item_code', 'measure_code']);
        });

        Schema::table('app.finance_journal_line', function (Blueprint $table) {
            $table->decimal('meas_weight', 16, 4)->nullable()->after('measure_code');
        });

        Schema::table('app.finance_ledger_line', function (Blueprint $table) {
            $table->decimal('meas_weight', 16, 4)->nullable()->after('measure_code');
        });
    }

    public function down(): void
    {
        Schema::table('app.finance_ledger_line', function (Blueprint $table) {
            $table->dropColumn('meas_weight');
        });

        Schema::table('app.finance_journal_line', function (Blueprint $table) {
            $table->dropColumn('meas_weight');
        });

        Schema::table('app.items_measurement', function (Blueprint $table) {
            $table->unique(['item_code', 'measure_code']);
        });
    }
};
