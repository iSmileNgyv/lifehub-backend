<?php

namespace Database\Seeders;

use App\Models\NumberSeries;
use Illuminate\Database\Seeder;

class NumberSeriesSeeder extends Seeder
{
    public function run(): void
    {
        $series = [
            ['code' => 'TRADING', 'name' => 'Trading jurnalı', 'prefix' => 'TJ_'],
        ];

        foreach ($series as $s) {
            NumberSeries::updateOrCreate(
                ['code' => $s['code']],
                [
                    'name' => $s['name'],
                    'prefix' => $s['prefix'],
                    'padding' => 4,
                    'start_no' => 1,
                    'end_no' => 9999,
                    'increment' => 1,
                ],
            );
        }
    }
}
