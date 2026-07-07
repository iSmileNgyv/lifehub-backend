<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Məhsul barkodları — bir məhsulun çox barkodu, bir barkod bir məhsula. */
    public function up(): void
    {
        Schema::create('app.item_barcodes', function (Blueprint $table) {
            $table->string('barcode')->primary();
            $table->string('item_code');
            $table->timestamps();

            $table->index('item_code');
            $table->foreign('item_code')->references('code')->on('app.items')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app.item_barcodes');
    }
};
