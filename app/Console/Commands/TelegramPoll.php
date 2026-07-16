<?php

namespace App\Console\Commands;

use App\Telegram\TelegramRouter;
use App\Telegram\TelegramService;
use Illuminate\Console\Command;

/** Dev: long-polling getUpdates → router. Açıq URL/webhook lazım deyil. */
class TelegramPoll extends Command
{
    protected $signature = 'telegram:poll';

    protected $description = 'Telegram getUpdates polling (dev üçün)';

    public function handle(TelegramService $tg, TelegramRouter $router): int
    {
        if (! $tg->configured()) {
            $this->error('TELEGRAM_BOT_TOKEN təyin edilməyib.');

            return self::FAILURE;
        }

        $tg->deleteWebhook(); // polling üçün webhook aktivsizləşdirilir
        $this->info('Telegram polling başladı… (dayandır: Ctrl+C)');

        $offset = 0;
        while (true) {
            foreach ($tg->getUpdates($offset) as $update) {
                $offset = ((int) ($update['update_id'] ?? 0)) + 1;
                try {
                    $router->handle($update);
                } catch (\Throwable $e) {
                    $this->error($e->getMessage());
                    report($e);
                }
            }
        }
    }
}
