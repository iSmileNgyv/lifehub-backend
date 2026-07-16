<?php

namespace App\Telegram;

use App\Models\User;

/** Bir update-in kontekst-i: chat, user (bağlıdırsa), cari mesaj/callback + göndərmə köməkçiləri. */
class TelegramContext
{
    public function __construct(
        public readonly TelegramService $tg,
        public readonly int|string $chatId,
        public readonly ?User $user = null,
        public readonly ?int $messageId = null,
        public readonly ?string $callbackId = null,
    ) {}

    /**
     * @param  array<int, array<int, array<string, string>>>|null  $buttons
     */
    public function say(string $text, ?array $buttons = null): void
    {
        $this->tg->sendMessage($this->chatId, $text, $buttons);
    }

    /**
     * @param  array<int, array<int, array<string, string>>>|null  $buttons
     */
    public function photo(string $bytes, string $filename, ?string $caption = null, ?array $buttons = null): void
    {
        $this->tg->sendPhoto($this->chatId, $bytes, $filename, $caption, $buttons);
    }

    public function answer(?string $text = null): void
    {
        if ($this->callbackId) {
            $this->tg->answerCallback($this->callbackId, $text);
        }
    }

    /** Cari mesajın düymələrini sil (təkrar basmasın). */
    public function clearButtons(): void
    {
        if ($this->messageId) {
            $this->tg->clearButtons($this->chatId, $this->messageId);
        }
    }
}
