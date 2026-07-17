<?php

namespace Database\Seeders;

use App\Models\Operation;
use Illuminate\Database\Seeder;

/**
 * Əməliyyat kataloqu — HƏQIQƏT MƏNBƏYI buradır (yalnız real, istifadə olunan op-lar).
 * LifeHub generic baza: yalnız sistem/idarəetmə modulları. Biznes modulları sonra əlavə olunacaq.
 * description çoxdillidir (JSONB).
 */
class OperationSeeder extends Seeder
{
    /** @var array<string, array<int, string>> module => [action, ...] */
    private array $catalog = [
        'dashboard' => ['VIEW'],
        'trading' => ['VIEW', 'CREATE', 'UPDATE', 'DELETE', 'POST', 'FORMULA_MANAGE'],
        'cashdesk' => ['VIEW', 'CREATE', 'UPDATE', 'DELETE', 'LEDGER_VIEW'],
        'measure' => ['VIEW', 'CREATE', 'UPDATE', 'DELETE'],
        'category' => ['VIEW', 'CREATE', 'UPDATE', 'DELETE'],
        'fincategory' => ['VIEW', 'CREATE', 'UPDATE', 'DELETE'],
        'finance' => ['VIEW', 'CREATE', 'UPDATE', 'DELETE', 'POST'],
        'product' => ['VIEW', 'CREATE', 'UPDATE', 'DELETE'],
        'vehicle' => ['VIEW', 'CREATE', 'UPDATE', 'DELETE'],
        'study' => ['VIEW', 'CREATE', 'UPDATE', 'DELETE'],
        'role' => ['VIEW', 'CREATE', 'UPDATE', 'DELETE', 'ACCESS_MANAGE'],
        'user' => ['VIEW', 'CREATE', 'UPDATE', 'ROLE_ASSIGN', 'TELEGRAM'],
        'language' => ['VIEW', 'MANAGE'],
        'settings' => ['VIEW', 'MANAGE'],
    ];

    /** @var array<string, array<string, string>> module => {az,en,ru} */
    private array $moduleLabels = [
        'dashboard' => ['az' => 'İdarə paneli', 'en' => 'Dashboard', 'ru' => 'Панель'],
        'trading' => ['az' => 'Trading', 'en' => 'Trading', 'ru' => 'Трейдинг'],
        'cashdesk' => ['az' => 'Kassa', 'en' => 'Cash desk', 'ru' => 'Касса'],
        'measure' => ['az' => 'Ölçü vahidi', 'en' => 'Measure unit', 'ru' => 'Ед. измерения'],
        'category' => ['az' => 'Kateqoriya', 'en' => 'Category', 'ru' => 'Категория'],
        'fincategory' => ['az' => 'Maliyyə kateqoriyası', 'en' => 'Finance category', 'ru' => 'Финансовая категория'],
        'finance' => ['az' => 'Maliyyə jurnalı', 'en' => 'Finance journal', 'ru' => 'Финансовый журнал'],
        'product' => ['az' => 'Məhsul', 'en' => 'Product', 'ru' => 'Товар'],
        'vehicle' => ['az' => 'Maşın', 'en' => 'Vehicle', 'ru' => 'Автомобиль'],
        'study' => ['az' => 'Öyrənmə', 'en' => 'Study', 'ru' => 'Обучение'],
        'role' => ['az' => 'Rol', 'en' => 'Role', 'ru' => 'Роль'],
        'user' => ['az' => 'İstifadəçi', 'en' => 'User', 'ru' => 'Пользователь'],
        'language' => ['az' => 'Dil', 'en' => 'Language', 'ru' => 'Язык'],
        'settings' => ['az' => 'Parametrlər', 'en' => 'Settings', 'ru' => 'Настройки'],
    ];

    /** @var array<string, array<string, string>> action => {az,en,ru} */
    private array $actionLabels = [
        'VIEW' => ['az' => 'baxış', 'en' => 'view', 'ru' => 'просмотр'],
        'CREATE' => ['az' => 'yaratma', 'en' => 'create', 'ru' => 'создание'],
        'UPDATE' => ['az' => 'yeniləmə', 'en' => 'update', 'ru' => 'обновление'],
        'DELETE' => ['az' => 'silmə', 'en' => 'delete', 'ru' => 'удаление'],
        'ACCESS_MANAGE' => ['az' => 'icazələrin idarəsi', 'en' => 'access management', 'ru' => 'управление доступом'],
        'ROLE_ASSIGN' => ['az' => 'rol təyini', 'en' => 'role assignment', 'ru' => 'назначение ролей'],
        'MANAGE' => ['az' => 'idarəetmə', 'en' => 'management', 'ru' => 'управление'],
        'POST' => ['az' => 'post etmə', 'en' => 'post', 'ru' => 'проведение'],
        'FORMULA_MANAGE' => ['az' => 'formula idarəsi', 'en' => 'formula management', 'ru' => 'управление формулами'],
        'LEDGER_VIEW' => ['az' => 'kitabça baxışı', 'en' => 'ledger view', 'ru' => 'просмотр книги'],
        'TELEGRAM' => ['az' => 'Telegram bağlama', 'en' => 'Telegram linking', 'ru' => 'привязка Telegram'],
    ];

    /** Anbar seçən operationlar — LifeHub-da yoxdur (generic baza). */
    private array $stockOps = [];

    public function run(): void
    {
        $codes = [];

        foreach ($this->catalog as $module => $actions) {
            foreach ($actions as $action) {
                $code = strtoupper($module).'_'.$action;
                $codes[] = $code;

                $description = [];
                foreach (['az', 'en', 'ru'] as $lang) {
                    $description[$lang] = $this->moduleLabels[$module][$lang].' — '.$this->actionLabels[$action][$lang];
                }

                Operation::updateOrCreate(
                    ['code' => $code],
                    ['description' => $description, 'module' => $module, 'is_stock' => in_array($code, $this->stockOps, true)],
                );
            }
        }

        // Kataloqda olmayan (köhnə) op-ları sil — role_access cascade
        Operation::whereNotIn('code', $codes)->delete();
    }
}
