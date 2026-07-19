<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Koloda paylaşımı (copy/fork semantikası).
     * - deck_shares = sahibin yaratdığı paylaşım kodu (mənbə kolodaya işarə).
     * - İdxal edən istifadəçidə tam müstəqil kopya yaranır (öz owner_uid-i ilə).
     * - Nəsil izi (source_*): təkrar idxalda (yenilə) yeni kartları progressi itirmədən əlavə etmək üçün.
     */
    public function up(): void
    {
        Schema::create('app.deck_shares', function (Blueprint $table) {
            $table->string('code')->primary();     // paylaşılan qısa kod
            $table->string('deck_uid');            // mənbə koloda
            $table->string('owner_uid');           // paylaşan istifadəçi
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->foreign('deck_uid')->references('uid')->on('app.decks')->cascadeOnDelete();
            $table->index('deck_uid');
            $table->index('owner_uid');
        });

        Schema::table('app.decks', function (Blueprint $table) {
            // Bu koloda hansı mənbədən idxal olunub (kopyadırsa doludur).
            $table->string('source_deck_uid')->nullable()->after('template_uid');
            $table->string('source_share_code')->nullable()->after('source_deck_uid');
            $table->index('source_deck_uid');
        });

        Schema::table('app.cards', function (Blueprint $table) {
            // Bu kart mənbə kolodadakı hansı kartın kopyasıdır (təkrar idxalda dublikatı önləmək üçün).
            $table->string('source_card_uid')->nullable()->after('deck_uid');
            $table->index('source_card_uid');
        });

        Schema::table('app.card_templates', function (Blueprint $table) {
            // Şablon kopyası: eyni mənbə şablonu təkrar idxalda yenidən yaratmamaq üçün.
            $table->string('source_template_uid')->nullable()->after('owner_uid');
            $table->index('source_template_uid');
        });
    }

    public function down(): void
    {
        Schema::table('app.card_templates', function (Blueprint $table) {
            $table->dropColumn('source_template_uid');
        });
        Schema::table('app.cards', function (Blueprint $table) {
            $table->dropColumn('source_card_uid');
        });
        Schema::table('app.decks', function (Blueprint $table) {
            $table->dropColumn(['source_deck_uid', 'source_share_code']);
        });
        Schema::dropIfExists('app.deck_shares');
    }
};
