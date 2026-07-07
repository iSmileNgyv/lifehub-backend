<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app.measurements', function (Blueprint $table) {
            $table->string('code')->primary();   // EDED, LT, KM
            $table->jsonb('name');               // çoxdilli
            $table->boolean('in_use')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app.measurements');
    }
};
