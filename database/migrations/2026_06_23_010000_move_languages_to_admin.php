<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * languages reyestri app → admin (system-config). Mövcud DB üçün köçürmə;
     * fresh-də create onsuz da admin-də yaranır (bu IF EXISTS no-op olur).
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE IF EXISTS app.languages SET SCHEMA admin');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE IF EXISTS admin.languages SET SCHEMA app');
    }
};
