<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Yanacaq doldurmaları: tarix + probeq + litr + məbləğ → təxmini sərfiyyat (L/100km). */
    public function up(): void
    {
        Schema::create('app.vehicle_fuel', function (Blueprint $table) {
            $table->string('uid')->primary();
            $table->string('vehicle_uid');
            $table->date('date');
            $table->decimal('odometer_km', 12, 2);   // doldurma anındakı probeq (KM)
            $table->decimal('liters', 10, 2);        // neçə litr
            $table->decimal('amount', 14, 2)->nullable(); // manat (xərc)
            $table->string('note')->nullable();
            $table->timestamps();

            $table->foreign('vehicle_uid')->references('uid')->on('app.vehicles')->cascadeOnDelete();
            $table->index(['vehicle_uid', 'odometer_km']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app.vehicle_fuel');
    }
};
