<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Transfer üçün hədəf hesab (yalnız entry_type=transfer-də dolur). */
    public function up(): void
    {
        Schema::table('app.finance_journal_entry', function (Blueprint $table) {
            $table->string('to_cash_desk_code')->nullable()->after('cash_desk_code');
        });
    }

    public function down(): void
    {
        Schema::table('app.finance_journal_entry', function (Blueprint $table) {
            $table->dropColumn('to_cash_desk_code');
        });
    }
};
