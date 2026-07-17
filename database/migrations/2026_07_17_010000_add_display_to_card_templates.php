<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Template-in xarici kanal görünüşü (Telegram / Extension) — hər kanal üçün
     * hansı sahələr, hansı sıra, ön/arxa. NULL → default (field.side işlədilir).
     * Nümunə: {"telegram":{"front":["word"],"back":["translation"]}, "extension":{...}}
     */
    public function up(): void
    {
        Schema::table('app.card_templates', function (Blueprint $table) {
            $table->jsonb('display')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('app.card_templates', function (Blueprint $table) {
            $table->dropColumn('display');
        });
    }
};
