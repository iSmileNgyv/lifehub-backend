<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Multi-tenant: hər biznes cədvəlinə owner_uid. Mövcud data ilk istifadəçiyə təyin olunur. */
    private array $tables = [
        'app.items', 'app.item_categories', 'app.items_measurement', 'app.measurements',
        'app.cash_desk', 'app.cash_ledger_entry',
        'app.finance_categories', 'app.finance_journal', 'app.finance_journal_entry',
        'app.finance_ledger_entry', 'app.finance_journal_line', 'app.finance_ledger_line',
        'app.vehicles', 'app.vehicle_readings', 'app.vehicle_services', 'app.vehicle_expenses', 'app.vehicle_fuel',
        'app.trading_formulas', 'app.trading_journal', 'app.trading_journal_entry', 'app.trading_ledger_entry',
        'app.cards',
    ];

    public function up(): void
    {
        $adminUid = DB::table('admin.users')->orderBy('created_at')->value('uid');

        foreach ($this->tables as $table) {
            if (! Schema::hasColumn($table, 'owner_uid')) {
                Schema::table($table, function ($t) {
                    $t->string('owner_uid')->nullable()->index();
                });
            }
            // Mövcud data ilk istifadəçiyə (admin) təyin olunur
            if ($adminUid) {
                DB::table($table)->whereNull('owner_uid')->update(['owner_uid' => $adminUid]);
            }
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $table) {
            if (Schema::hasColumn($table, 'owner_uid')) {
                Schema::table($table, function ($t) {
                    $t->dropColumn('owner_uid');
                });
            }
        }
    }
};
