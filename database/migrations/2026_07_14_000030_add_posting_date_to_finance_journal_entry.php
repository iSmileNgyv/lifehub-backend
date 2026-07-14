<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Hər jurnal sətrinin öz tarixi ola bilər (default = jurnal tarixi). */
    public function up(): void
    {
        Schema::table('app.finance_journal_entry', function (Blueprint $table) {
            $table->date('posting_date')->nullable()->after('jnl_code');
        });
    }

    public function down(): void
    {
        Schema::table('app.finance_journal_entry', function (Blueprint $table) {
            $table->dropColumn('posting_date');
        });
    }
};
