<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Transaction nömrəsi sayğacı — illik sıfırlanan (year*1e9 + last_no). */
    public function up(): void
    {
        Schema::create('app.transaction_seq', function (Blueprint $table) {
            $table->integer('year')->primary();
            $table->bigInteger('last_no')->default(0);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app.transaction_seq');
    }
};
