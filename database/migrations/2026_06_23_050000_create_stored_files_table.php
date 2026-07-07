<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Saxlanan fayllar reyestri. items.image bu cədvəlin `uid`-ini tutur.
     * `driver` sahəsi sistemə faylın harada (local/s3/azure) olduğunu bildirir.
     */
    public function up(): void
    {
        Schema::create('app.stored_files', function (Blueprint $table) {
            $table->ulid('uid')->primary();
            $table->string('driver');        // local / s3 / azure
            $table->string('path');          // driver içində açar/yol
            $table->string('original_name')->nullable();
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app.stored_files');
    }
};
