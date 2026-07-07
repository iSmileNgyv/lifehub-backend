<?php

namespace App\Support;

use App\Models\Language;

/**
 * Çoxdilli (JSONB) sahələr üçün köməkçi — dil reyestrinə (admin.languages) bağlı.
 */
class Translatable
{
    /** @return array<int, string> */
    public static function activeCodes(): array
    {
        return Language::where('is_active', true)->orderBy('sort_order')->pluck('code')->all();
    }

    public static function defaultCode(): string
    {
        return Language::where('is_default', true)->value('code')
            ?? (self::activeCodes()[0] ?? 'az');
    }

    /**
     * Validasiya qaydaları: massiv + default dil mütləq.
     *
     * @return array<string, mixed>
     */
    public static function rules(string $field): array
    {
        $default = self::defaultCode();

        return [
            $field => ['required', 'array'],
            "{$field}.{$default}" => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * Yalnız aktiv dil açarlarını saxla (boşları at).
     *
     * @param  array<string, mixed>  $value
     * @return array<string, string>
     */
    public static function sanitize(array $value): array
    {
        $allowed = array_flip(self::activeCodes());
        $clean = [];
        foreach (array_intersect_key($value, $allowed) as $code => $text) {
            $text = is_string($text) ? trim($text) : '';
            if ($text !== '') {
                $clean[$code] = $text;
            }
        }

        return $clean;
    }
}
