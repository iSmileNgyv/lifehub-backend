<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Extension üçün ayrı öyrənmə rejimi (telegram `mode`-dan müstəqil). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin.telegram_settings', function (Blueprint $table) {
            $table->string('ext_mode', 20)->default('flashcard')->after('mode');
        });
    }

    public function down(): void
    {
        Schema::table('admin.telegram_settings', function (Blueprint $table) {
            $table->dropColumn('ext_mode');
        });
    }
};
