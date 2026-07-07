<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Item üzrə vahid çevirmələri: 1 measure_code = meas_weight × base_measure_code. */
    public function up(): void
    {
        Schema::create('app.items_measurement', function (Blueprint $table) {
            $table->ulid('uid')->primary();
            $table->string('item_code');
            $table->string('base_measure_code');
            $table->string('measure_code');
            $table->decimal('meas_weight', 16, 4);
            $table->boolean('in_use')->default(false);
            $table->timestamps();

            $table->unique(['item_code', 'measure_code']);
            $table->index('item_code');

            $table->foreign('item_code')->references('code')->on('app.items')->cascadeOnDelete();
            $table->foreign('base_measure_code')->references('code')->on('app.measurements')->restrictOnDelete();
            $table->foreign('measure_code')->references('code')->on('app.measurements')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app.items_measurement');
    }
};
