<?php

namespace App\Console\Commands;

use App\Models\TelegramSetting;
use App\Models\User;
use App\Telegram\Modules\StudyTelegramModule;
use App\Telegram\TelegramContext;
use App\Telegram\TelegramService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;

/** Proaktiv "qəfil soruşma" — settings-ə görə (deck, interval, aktiv saatlar). Scheduler dəqiqəbaşı işlədir. */
class TelegramStudyPush extends Command
{
    protected $signature = 'telegram:study-push';

    protected $description = 'Aktiv istifadəçilərə due flashcard push (settings-driven)';

    public function handle(TelegramService $tg, StudyTelegramModule $study): int
    {
        if (! $tg->configured()) {
            return self::SUCCESS;
        }

        $now = now();
        $cur = $now->format('H:i');

        foreach (TelegramSetting::where('study_enabled', true)->get() as $s) {
            if (! $this->inWindow($cur, substr((string) $s->active_from, 0, 5), substr((string) $s->active_to, 0, 5))) {
                continue;
            }
            if ($s->last_pushed_at && $s->last_pushed_at->copy()->addMinutes($s->interval_min)->gt($now)) {
                continue;
            }
            $user = User::where('uid', $s->owner_uid)->whereNotNull('telegram_chat_id')->first();
            if (! $user) {
                continue;
            }

            Auth::setUser($user);
            try {
                $ctx = new TelegramContext($tg, $user->telegram_chat_id, $user);
                if ($study->pushDue($ctx, $s->cards_per_push) > 0) {
                    $s->forceFill(['last_pushed_at' => $now])->save();
                }
            } catch (\Throwable $e) {
                report($e);
            } finally {
                Auth::forgetUser();
            }
        }

        return self::SUCCESS;
    }

    /** cur (H:i) aralıqda? from<=to normal; from>to gecəaşan. */
    private function inWindow(string $cur, string $from, string $to): bool
    {
        return $from <= $to ? ($cur >= $from && $cur <= $to) : ($cur >= $from || $cur <= $to);
    }
}
