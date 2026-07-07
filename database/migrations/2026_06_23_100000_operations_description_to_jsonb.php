<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * operations.description → çoxdilli JSONB. Köhnə string dəyərlər json deyil,
     * ona görə sütun drop+add olunur; OperationSeeder yenidən doldurur.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE admin.operations DROP COLUMN description');
        DB::statement("ALTER TABLE admin.operations ADD COLUMN description jsonb NOT NULL DEFAULT '{}'::jsonb");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE admin.operations DROP COLUMN description');
        DB::statement("ALTER TABLE admin.operations ADD COLUMN description varchar(255) NOT NULL DEFAULT ''");
    }
};
