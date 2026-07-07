<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Maşınlar. DAXİLDƏ hər məsafə KM-də saxlanır (kanonik).
     * unit = istifadəçinin odometr vahidi (mi/km) — giriş/göstərişdə çevrilir (1 mi = 1.609344 km).
     * cari km oxunuş logundan gəlir; avg_km_per_day yalnız fallback (log azdırsa).
     */
    public function up(): void
    {
        Schema::create('app.vehicles', function (Blueprint $table) {
            $table->string('uid')->primary();      // ULID
            $table->string('name');
            $table->string('plate')->nullable();
            $table->string('unit', 2)->default('km'); // 'km' | 'mi' — göstəriş/giriş vahidi
            $table->decimal('avg_km_per_day', 10, 2)->nullable(); // fallback (log <2 oxunuş), KM
            $table->string('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app.vehicles');
    }
};
