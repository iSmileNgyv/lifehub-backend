<?php

namespace App\Console\Commands;

use App\Telegram\TelegramService;
use Illuminate\Console\Command;

/** Prod: Telegram webhook-u qur/sil. Nümunə: php artisan telegram:set-webhook https://domain.com */
class TelegramSetWebhook extends Command
{
    protected $signature = 'telegram:set-webhook {url? : Webhook base URL, məs. https://domain.com} {--delete : Webhook-u sil}';

    protected $description = 'Telegram webhook qur (prod) və ya sil';

    public function handle(TelegramService $tg): int
    {
        if (! $tg->configured()) {
            $this->error('TELEGRAM_BOT_TOKEN yoxdur.');

            return self::FAILURE;
        }

        if ($this->option('delete')) {
            $tg->deleteWebhook();
            $this->info('Webhook silindi (polling üçün).');

            return self::SUCCESS;
        }

        $base = rtrim((string) ($this->argument('url') ?: config('app.url')), '/');
        $secret = (string) config('services.telegram.webhook_secret');
        $url = "{$base}/api/v1/telegram/webhook/{$secret}";

        $res = $tg->setWebhook($url, $secret);
        if ($res) {
            $this->info("✅ Webhook quruldu:\n  {$url}");

            return self::SUCCESS;
        }
        $this->error('Xəta — laravel.log yoxla (URL HTTPS + public olmalıdır).');

        return self::FAILURE;
    }
}
