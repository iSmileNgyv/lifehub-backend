<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Telegram bot inteqrasiyası.
     * - users-ə chat bağlama sahələri (birdəfəlik kodla).
     * - telegram_settings: owner üzrə bot davranışı (study push — deck, tezlik, aktiv saatlar).
     */
    public function up(): void
    {
        Schema::table('admin.users', function (Blueprint $table) {
            $table->string('telegram_chat_id')->nullable()->index();
            $table->string('telegram_link_code')->nullable();
            $table->timestamp('telegram_link_expires_at')->nullable();
        });

        Schema::create('admin.telegram_settings', function (Blueprint $table) {
            $table->string('owner_uid')->primary();      // = user uid
            $table->boolean('study_enabled')->default(false);
            $table->string('study_deck_uid')->nullable(); // null = bütün due kartlar
            $table->integer('interval_min')->default(60); // push tezliyi (dəq)
            $table->time('active_from')->default('09:00');
            $table->time('active_to')->default('22:00');
            $table->integer('cards_per_push')->default(1);
            $table->timestamp('last_pushed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin.telegram_settings');
        Schema::table('admin.users', function (Blueprint $table) {
            $table->dropColumn(['telegram_chat_id', 'telegram_link_code', 'telegram_link_expires_at']);
        });
    }
};
