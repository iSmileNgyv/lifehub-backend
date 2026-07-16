<?php

namespace App\Telegram;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/** Telegram Bot API üzərində nazik örtük (raw HTTP). */
class TelegramService
{
    private string $token;

    public function __construct()
    {
        $this->token = (string) config('services.telegram.token');
    }

    public function configured(): bool
    {
        return $this->token !== '';
    }

    private function api(): PendingRequest
    {
        return Http::baseUrl("https://api.telegram.org/bot{$this->token}")->acceptJson();
    }

    /**
     * @param  array<int, array<int, array<string, string>>>|null  $buttons  inline keyboard (sətir → düymələr)
     * @return array<string, mixed>|null
     */
    public function sendMessage(int|string $chatId, string $text, ?array $buttons = null): ?array
    {
        return $this->call('sendMessage', array_filter([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'HTML',
            'reply_markup' => $buttons ? json_encode(['inline_keyboard' => $buttons]) : null,
        ]));
    }

    /**
     * Şəkli baytlarla yüklə (dev-də də işləyir — public URL lazım deyil).
     *
     * @param  array<int, array<int, array<string, string>>>|null  $buttons
     * @return array<string, mixed>|null
     */
    public function sendPhoto(int|string $chatId, string $bytes, string $filename, ?string $caption = null, ?array $buttons = null): ?array
    {
        $req = $this->api()->attach('photo', $bytes, $filename);
        $res = $req->post('sendPhoto', array_filter([
            'chat_id' => $chatId,
            'caption' => $caption,
            'parse_mode' => 'HTML',
            'reply_markup' => $buttons ? json_encode(['inline_keyboard' => $buttons]) : null,
        ]));

        return $this->ok($res->json(), 'sendPhoto');
    }

    /** Düymələri sil (istifadədən sonra). */
    public function clearButtons(int|string $chatId, int $messageId): void
    {
        $this->call('editMessageReplyMarkup', ['chat_id' => $chatId, 'message_id' => $messageId]);
    }

    public function answerCallback(string $callbackId, ?string $text = null): void
    {
        $this->call('answerCallbackQuery', array_filter(['callback_query_id' => $callbackId, 'text' => $text]));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getUpdates(int $offset, int $timeout = 25): array
    {
        $res = $this->api()->timeout($timeout + 10)->get('getUpdates', ['offset' => $offset, 'timeout' => $timeout]);
        $body = $res->json();

        return is_array($body['result'] ?? null) ? $body['result'] : [];
    }

    public function setWebhook(string $url, string $secret): mixed
    {
        return $this->call('setWebhook', ['url' => $url, 'secret_token' => $secret, 'drop_pending_updates' => true]);
    }

    public function deleteWebhook(): mixed
    {
        return $this->call('deleteWebhook', ['drop_pending_updates' => true]);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function call(string $method, array $params): mixed
    {
        return $this->ok($this->api()->post($method, $params)->json(), $method);
    }

    /**
     * @param  array<string, mixed>|null  $body
     */
    private function ok(?array $body, string $method): mixed
    {
        if (! ($body['ok'] ?? false)) {
            Log::warning("Telegram {$method} failed", ['response' => $body]);

            return null;
        }

        return $body['result'] ?? null;
    }
}
