<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Custom role / icazə sistemi — bax DESIGN.md §13.
     */
    public function up(): void
    {
        // Rollar — açar `code` (məs. SHISHA, OWNER)
        Schema::create('admin.roles', function (Blueprint $table) {
            $table->string('code')->primary();
            $table->string('name');
            $table->timestamps();
        });

        // Əməliyyat kataloqu — kodla seed olunur, interfeysdən dəyişilmir
        Schema::create('admin.operations', function (Blueprint $table) {
            $table->string('code')->primary();
            $table->string('description');
            $table->string('module')->index();
            $table->boolean('is_stock')->default(false); // generic bazada həmişə false
            $table->timestamps();
        });

        // user ↔ role (çox-çoxa)
        Schema::create('admin.user_role', function (Blueprint $table) {
            $table->ulid('user_uid');
            $table->string('role_code');

            $table->primary(['user_uid', 'role_code']);
            $table->foreign('user_uid')->references('uid')->on('admin.users')->cascadeOnDelete();
            $table->foreign('role_code')->references('code')->on('admin.roles')->cascadeOnDelete();
        });

        // role ↔ operation icazələri (access: 1 = açıq, 0 = qıfıllı/verilməyib)
        Schema::create('admin.role_access', function (Blueprint $table) {
            $table->string('role_code');
            $table->string('operation_code');
            $table->boolean('access')->default(true);
            $table->timestamps();

            $table->primary(['role_code', 'operation_code']);
            $table->foreign('role_code')->references('code')->on('admin.roles')->cascadeOnDelete();
            $table->foreign('operation_code')->references('code')->on('admin.operations')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin.role_access');
        Schema::dropIfExists('admin.user_role');
        Schema::dropIfExists('admin.operations');
        Schema::dropIfExists('admin.roles');
    }
};
