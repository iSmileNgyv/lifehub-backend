<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Xidmət/dəyişmə qeydləri: hansı hissə (item), nə vaxt/hansı probeqdə quraşdırılıb, ömrü.
     * Ömür km VƏ/yaxud ay (hansı əvvəl bitsə). km KM-də. active=false → dəyişilib (tarixçə).
     */
    public function up(): void
    {
        Schema::create('app.vehicle_services', function (Blueprint $table) {
            $table->string('uid')->primary();
            $table->string('vehicle_uid');
            $table->string('item_code')->nullable();       // katalogdan hissə
            $table->jsonb('item_name')->nullable();        // snapshot
            $table->date('installed_date');
            $table->decimal('installed_km', 12, 2);        // quraşdırma anındakı probeq (KM)
            $table->decimal('life_km', 12, 2)->nullable(); // ömür (KM)
            $table->integer('life_months')->nullable();    // ömür (ay)
            $table->string('note')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->foreign('vehicle_uid')->references('uid')->on('app.vehicles')->cascadeOnDelete();
            $table->index(['vehicle_uid', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app.vehicle_services');
    }
};
