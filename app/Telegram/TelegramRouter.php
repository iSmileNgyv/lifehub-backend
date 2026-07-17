<?php

namespace App\Telegram;

use App\Models\User;
use App\Telegram\Contracts\TelegramModule;
use App\Telegram\Modules\StudyTelegramModule;
use App\Telegram\Modules\TradingTelegramModule;
use Illuminate\Support\Facades\Auth;

/**
 * Gələn update-i düzgün modula yönləndirir + owner-scope təyin edir.
 * Bağlanmamış chat data görmür (yalnız /start bağlama). Modul əlavə etmək = modules()-a əlavə.
 */
class TelegramRouter
{
    public function __construct(private TelegramService $tg) {}

    /** @return array<int, TelegramModule> */
    private function modules(): array
    {
        return [
            app(StudyTelegramModule::class),
            app(TradingTelegramModule::class),
        ];
    }

    /** @return array<int, array<int, array<string, string>>> */
    private function mainMenu(): array
    {
        return [
            [['text' => '📚 Öyrən', 'callback_data' => 'st:learn']],
            [['text' => '💱 Ticarət', 'callback_data' => 'tr:start']],
        ];
    }

    /** @param array<string, mixed> $update */
    public function handle(array $update): void
    {
        if (! $this->tg->configured()) {
            return;
        }

        if (isset($update['callback_query'])) {
            $this->onCallback($update['callback_query']);

            return;
        }
        if (isset($update['message'])) {
            $this->onMessage($update['message']);
        }
    }

    /** @param array<string, mixed> $message */
    private function onMessage(array $message): void
    {
        $chatId = $message['chat']['id'] ?? null;
        if ($chatId === null) {
            return;
        }
        $text = trim((string) ($message['text'] ?? ''));

        // Bağlama axını — istənilən halda (bağlı olsun-olmasın)
        if (str_starts_with($text, '/start')) {
            $this->handleStart($chatId, trim(substr($text, 6)), $message);

            return;
        }

        $user = $this->resolveUser($chatId);
        $ctx = new TelegramContext($this->tg, $chatId, $user);
        if (! $user) {
            $this->promptLink($ctx);

            return;
        }

        if (str_starts_with($text, '/')) {
            $parts = explode(' ', ltrim($text, '/'), 2);
            $cmd = strtolower($parts[0]);
            $args = trim($parts[1] ?? '');
            $this->asUser($user, function () use ($ctx, $cmd, $args) {
                foreach ($this->modules() as $m) {
                    if (in_array($cmd, $m->commands(), true)) {
                        $m->onCommand($ctx, $cmd, $args);

                        return;
                    }
                }
                $ctx->say('🤖 Komanda tanınmadı.', $this->mainMenu());
            });

            return;
        }

        // Adi mətn — aktiv söhbət state-i varsa sahib modula ötür, yoxsa menyu
        $state = TelegramState::get($chatId);
        $this->asUser($user, function () use ($ctx, $text, $state) {
            if ($state) {
                foreach ($this->modules() as $m) {
                    if ($m->key() === $state['module']) {
                        $m->onText($ctx, $text);

                        return;
                    }
                }
            }
            $ctx->say('Menyu:', $this->mainMenu());
        });
    }

    /** @param array<string, mixed> $cb */
    private function onCallback(array $cb): void
    {
        $chatId = $cb['message']['chat']['id'] ?? null;
        $messageId = $cb['message']['message_id'] ?? null;
        $data = (string) ($cb['data'] ?? '');
        $callbackId = (string) ($cb['id'] ?? '');
        if ($chatId === null) {
            return;
        }

        $user = $this->resolveUser($chatId);
        $ctx = new TelegramContext($this->tg, $chatId, $user, $messageId, $callbackId);
        if (! $user) {
            $ctx->answer('Əvvəl bağla');
            $this->promptLink($ctx);

            return;
        }

        $this->asUser($user, function () use ($ctx, $data) {
            foreach ($this->modules() as $m) {
                if ($m->ownsCallback($data)) {
                    $m->onCallback($ctx, $data);

                    return;
                }
            }
            $ctx->answer();
        });
    }

    /** /start [kod] — bağla və ya salamla. */
    private function handleStart(int|string $chatId, string $code, array $message): void
    {
        $ctx = new TelegramContext($this->tg, $chatId, $this->resolveUser($chatId));

        if ($code !== '') {
            $user = User::where('telegram_link_code', $code)
                ->where('telegram_link_expires_at', '>', now())
                ->first();
            if (! $user) {
                $ctx->say('❌ Kod yanlış və ya vaxtı keçib. LifeHub-dan yeni kod al.');

                return;
            }
            $user->forceFill([
                'telegram_chat_id' => (string) $chatId,
                'telegram_link_code' => null,
                'telegram_link_expires_at' => null,
            ])->save();
            $ctx->say("✅ Bağlandı, <b>{$user->username}</b>!", $this->mainMenu());

            return;
        }

        if ($ctx->user) {
            $ctx->say('Salam! 👋', $this->mainMenu());
        } else {
            $this->promptLink($ctx);
        }
    }

    private function promptLink(TelegramContext $ctx): void
    {
        $ctx->say("👋 Salam! Bu botu hesabınla bağlamaq üçün LifeHub-da <b>Telegram</b> səhifəsindən kod al və bura <code>/start KOD</code> yaz.");
    }

    private function resolveUser(int|string $chatId): ?User
    {
        return User::where('telegram_chat_id', (string) $chatId)->first();
    }

    /** Modulu owner-scope altında işlət (BelongsToOwner cari user-lə filtrlənir). */
    private function asUser(User $user, callable $fn): void
    {
        Auth::setUser($user);
        try {
            $fn();
        } finally {
            Auth::forgetUser();
        }
    }
}
