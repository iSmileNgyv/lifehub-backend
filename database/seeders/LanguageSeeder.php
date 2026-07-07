<?php

namespace Database\Seeders;

use App\Models\Language;
use Illuminate\Database\Seeder;

class LanguageSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['code' => 'az', 'name' => 'Azərbaycanca', 'is_default' => true, 'sort_order' => 1],
            ['code' => 'en', 'name' => 'English', 'is_default' => false, 'sort_order' => 2],
            ['code' => 'ru', 'name' => 'Русский', 'is_default' => false, 'sort_order' => 3],
        ];

        foreach ($rows as $r) {
            Language::updateOrCreate(
                ['code' => $r['code']],
                ['name' => $r['name'], 'is_active' => true, 'is_default' => $r['is_default'], 'sort_order' => $r['sort_order']],
            );
        }
    }
}
