<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Büdcə / hədəf (aylıq). Üç növ:
     *  - category_expense: kateqoriya üzrə aylıq xərc limiti (category_code dolu).
     *  - overall_expense: ümumi aylıq xərc tavanı (category_code NULL).
     *  - income_target: aylıq gəlir hədəfi (category_code NULL).
     * Report-da seçilmiş dövrə proporsional müqayisə olunur.
     */
    public function up(): void
    {
        Schema::create('app.finance_budgets', function (Blueprint $table) {
            $table->string('uid')->primary();
            $table->string('owner_uid');
            $table->string('kind');                     // category_expense | overall_expense | income_target
            $table->string('category_code')->nullable();
            $table->decimal('amount_lcy', 18, 2)->default(0);
            $table->timestamps();

            $table->index('owner_uid');
            $table->index(['owner_uid', 'kind', 'category_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app.finance_budgets');
    }
};
