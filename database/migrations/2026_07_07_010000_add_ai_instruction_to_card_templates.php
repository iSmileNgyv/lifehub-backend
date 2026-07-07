<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app.card_templates', function (Blueprint $table) {
            // AI üçün ümumi təlimat (bütün sahələrə aid, məs. formatlaşdırma qaydası)
            $table->text('ai_instruction')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('app.card_templates', function (Blueprint $table) {
            $table->dropColumn('ai_instruction');
        });
    }
};
