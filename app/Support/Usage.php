<?php

namespace App\Support;

use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\ItemMeasurement;
use App\Models\Measurement;

/**
 * `in_use` bayraqlarını dəqiq hesablayır (front düyməni qabaqcadan deaktiv etsin).
 * LifeHub: yalnız item↔category, item↔measure. (Maşın xidməti sonra əlavə olunacaq.)
 */
class Usage
{
    public static function measurement(?string $code): void
    {
        if (! $code) {
            return;
        }
        $used = Item::where('base_measure_code', $code)->exists()
            || ItemMeasurement::where('measure_code', $code)->exists()
            || ItemMeasurement::where('base_measure_code', $code)->exists();
        Measurement::where('code', $code)->update(['in_use' => $used]);
    }

    public static function category(?string $code): void
    {
        if (! $code) {
            return;
        }
        ItemCategory::where('code', $code)->update([
            'in_use' => Item::where('category_code', $code)->exists(),
        ]);
    }
}
