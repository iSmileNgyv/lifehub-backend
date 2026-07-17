<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Öyrənmə parametrləri (bot + extension ortaq) — telegram_settings üzərinə:
     * mode (learning/flashcard) + extension konfiqurasiyası.
     */
    public function up(): void
    {
        Schema::table('admin.telegram_settings', function (Blueprint $table) {
            $table->string('mode')->default('flashcard');   // learning | flashcard
            $table->boolean('ext_enabled')->default(true);   // extension aktiv
            $table->integer('ext_rotate_sec')->default(45);  // kart dəyişmə (san)
            $table->integer('ext_notify_min')->default(20);  // extension bildiriş (dəq)
            $table->boolean('ext_notify')->default(true);    // extension bildiriş aç/söndür
        });
    }

    public function down(): void
    {
        Schema::table('admin.telegram_settings', function (Blueprint $table) {
            $table->dropColumn(['mode', 'ext_enabled', 'ext_rotate_sec', 'ext_notify_min', 'ext_notify']);
        });
    }
};
