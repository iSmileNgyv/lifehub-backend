<?php

namespace App\Telegram\Contracts;

use App\Telegram\TelegramContext;

/**
 * Bot modulu — hər funksional sahə (study, sonra finance və s.) bunu implement edir.
 * Router komanda/callback-ları uyğun modula yönləndirir. Yeni modul = yeni sinif + registry-ə əlavə.
 */
interface TelegramModule
{
    /** Modul açarı (söhbət state-ində sahiblik üçün): məs. 'study', 'trade'. */
    public function key(): string;

    /**
     * Əsas menyu düyməsi (icazəyə görə süzülür) və ya null.
     *
     * @return array{text:string, callback_data:string, op:string}|null
     */
    public function menuButton(): ?array;

    /** İşlətdiyi komandalar (slash-sız): məs. ['learn']. */
    public function commands(): array;

    /** Bu callback_data bu modula aiddir? (məs. str_starts_with($data, 'st:')) */
    public function ownsCallback(string $data): bool;

    /** Komanda işlə (bağlı user var). $args = komandadan sonrakı mətn. */
    public function onCommand(TelegramContext $ctx, string $command, string $args): void;

    /** Inline düymə (callback) işlə. */
    public function onCallback(TelegramContext $ctx, string $data): void;

    /** Mətn mesajı (yalnız bu modul söhbət state-ində olanda çağrılır). */
    public function onText(TelegramContext $ctx, string $text): void;
}
