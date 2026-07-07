<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Probeq oxunuşları (log) — çoxlu sətir, qeyri-bərabər aralarla ola bilər.
     * km KM-də (kanonik). Eyni gün üçün UPSERT (vehicle+date unikal).
     * Sürət (km/gün) bu loga çəkili reqressiya ilə hesablanır.
     */
    public function up(): void
    {
        Schema::create('app.vehicle_readings', function (Blueprint $table) {
            $table->string('uid')->primary();
            $table->string('vehicle_uid');
            $table->date('reading_date');
            $table->decimal('km', 12, 2);          // odometr (KM)
            $table->timestamps();

            $table->foreign('vehicle_uid')->references('uid')->on('app.vehicles')->cascadeOnDelete();
            $table->unique(['vehicle_uid', 'reading_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app.vehicle_readings');
    }
};
