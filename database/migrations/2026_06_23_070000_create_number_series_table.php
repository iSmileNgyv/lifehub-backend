<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Auto-kod (BC No. Series): prefix + sıfır-doldurulmuş artan nömrə.
     * Məs. VENDOR → VE_0001, VE_0002…
     */
    public function up(): void
    {
        Schema::create('admin.number_series', function (Blueprint $table) {
            $table->string('code')->primary();   // VENDOR, PURCHASE…
            $table->string('name');
            $table->string('prefix')->default('');
            $table->unsignedInteger('padding')->default(0); // rəqəm sayı (4 → 0001)
            $table->unsignedBigInteger('start_no')->default(1);
            $table->unsignedBigInteger('end_no')->nullable();
            $table->unsignedInteger('increment')->default(1);
            $table->unsignedBigInteger('last_no')->nullable(); // axırıncı verilmiş
            $table->boolean('in_use')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin.number_series');
    }
};
