<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Maşın məsrəfləri (təmir, sığorta, hissə və s.) — aylıq/illik xərc hesabatı üçün. */
    public function up(): void
    {
        Schema::create('app.vehicle_expenses', function (Blueprint $table) {
            $table->string('uid')->primary();
            $table->string('vehicle_uid');
            $table->date('date');
            $table->string('title');                 // nə üçün
            $table->decimal('amount', 14, 2);        // manat
            $table->string('note')->nullable();
            $table->timestamps();

            $table->foreign('vehicle_uid')->references('uid')->on('app.vehicles')->cascadeOnDelete();
            $table->index(['vehicle_uid', 'date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app.vehicle_expenses');
    }
};
