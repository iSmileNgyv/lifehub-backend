<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Trading satış formulası — pilləli. Hər pillə: {from, to, expr}.
     * expr dəyişəni `x` = müştərinin verdiyi manat. from/to = məbləğ aralığı (from<=x<to).
     * Bir aktiv formula (is_active); köhnə versiyalar tarixçə üçün qala bilər.
     */
    public function up(): void
    {
        Schema::create('app.trading_formulas', function (Blueprint $table) {
            $table->string('uid')->primary();     // ULID
            $table->string('name');
            $table->jsonb('tiers');                // [{from, to, expr}]
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app.trading_formulas');
    }
};
