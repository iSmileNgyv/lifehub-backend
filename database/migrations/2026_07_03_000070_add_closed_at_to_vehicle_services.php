<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Xidmət nə vaxt bağlandı (tarixçəyə keçdi) — 24 saatlıq «geri al» üçün. */
    public function up(): void
    {
        Schema::table('app.vehicle_services', function (Blueprint $table) {
            $table->timestamp('closed_at')->nullable()->after('active');
        });
    }

    public function down(): void
    {
        Schema::table('app.vehicle_services', function (Blueprint $table) {
            $table->dropColumn('closed_at');
        });
    }
};
