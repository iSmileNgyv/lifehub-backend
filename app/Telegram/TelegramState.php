<?php

namespace App\Telegram;

use Illuminate\Support\Facades\Cache;

/** Çoxaddımlı söhbət state-i (chat üzrə, cache-də). Modul: {module, step, data}. */
class TelegramState
{
    private static function key(int|string $chatId): string
    {
        return 'tg_state:'.$chatId;
    }

    /** @return array{module:string, step:string, data:array<string,mixed>}|null */
    public static function get(int|string $chatId): ?array
    {
        return Cache::get(self::key($chatId));
    }

    /** @param array<string, mixed> $data */
    public static function set(int|string $chatId, string $module, string $step, array $data = []): void
    {
        Cache::put(self::key($chatId), ['module' => $module, 'step' => $step, 'data' => $data], now()->addMinutes(30));
    }

    public static function clear(int|string $chatId): void
    {
        Cache::forget(self::key($chatId));
    }
}
