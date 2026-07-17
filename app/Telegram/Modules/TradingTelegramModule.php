<?php

namespace App\Telegram\Modules;

use App\Models\TradingFormula;
use App\Models\TradingJournal;
use App\Models\TradingLedgerEntry;
use App\Services\TradingPostingService;
use App\Support\FormulaEvaluator;
use App\Telegram\Contracts\TelegramModule;
use App\Telegram\TelegramContext;
use App\Telegram\TelegramState;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Trading — botdan draft jurnala al/sat sətri əlavə + statistika (post ETMİR, o web-də qalır).
 * Web-dəki funksionallığı işlədir: draft seç → Al/Sat → sətir. Söhbət state-i ilə çoxaddımlı.
 */
class TradingTelegramModule implements TelegramModule
{
    public function key(): string
    {
        return 'trade';
    }

    public function menuButton(): ?array
    {
        return ['text' => '💱 Ticarət', 'callback_data' => 'tr:start', 'op' => 'TRADING_VIEW'];
    }

    public function commands(): array
    {
        return ['trade', 'balance'];
    }

    public function ownsCallback(string $data): bool
    {
        return str_starts_with($data, 'tr:');
    }

    public function onCommand(TelegramContext $ctx, string $command, string $args): void
    {
        if ($command === 'balance') {
            $this->sendBalance($ctx);

            return;
        }
        $this->showJournals($ctx);
    }

    public function onCallback(TelegramContext $ctx, string $data): void
    {
        $parts = explode(':', $data); // tr:start | tr:j:CODE | tr:buy | tr:sell | tr:stats | tr:confirm | tr:cancel
        $action = $parts[1] ?? '';

        switch ($action) {
            case 'start':
                $ctx->answer();
                $this->showJournals($ctx);
                break;
            case 'j':
                $this->selectJournal($ctx, $parts[2] ?? '');
                break;
            case 'buy':
                $this->startEntry($ctx, 'buy');
                break;
            case 'sell':
                $this->startEntry($ctx, 'sell');
                break;
            case 'stats':
                $this->onStats($ctx);
                break;
            case 'confirm':
                $this->confirmEntry($ctx);
                break;
            case 'cancel':
                $this->cancel($ctx);
                break;
            default:
                $ctx->answer();
        }
    }

    public function onText(TelegramContext $ctx, string $text): void
    {
        $state = TelegramState::get($ctx->chatId);
        if (! $state) {
            return;
        }
        $step = $state['step'];
        $data = $state['data'];
        $num = $this->parseNum($text);

        if ($step === 'buy_manat' || $step === 'sell_manat') {
            if ($num <= 0) {
                $ctx->say('Düzgün məbləğ yaz (məs. 500).');

                return;
            }
            $data['manat'] = $num;

            if ($step === 'buy_manat') {
                TelegramState::set($ctx->chatId, 'trade', 'buy_usd', $data);
                $ctx->say('Neçə USD aldın?');

                return;
            }
            // sell → aktiv formuladan USD
            $active = TradingFormula::where('is_active', true)->first();
            if (! $active) {
                $ctx->say('⚠️ Aktiv formula yoxdur — sat üçün USD hesablana bilmir. Web-də formula aktivləşdir.');

                return;
            }
            $data['usd'] = round((float) FormulaEvaluator::apply($active->tiers, $num)['result'], 4);
            TelegramState::set($ctx->chatId, 'trade', 'confirm', $data);
            $this->askConfirm($ctx, $data);

            return;
        }

        if ($step === 'buy_usd') {
            if ($num <= 0) {
                $ctx->say('Düzgün USD miqdarı yaz (məs. 294).');

                return;
            }
            $data['usd'] = $num;
            TelegramState::set($ctx->chatId, 'trade', 'confirm', $data);
            $this->askConfirm($ctx, $data);
        }
    }

    /** Draft jurnalları düymə kimi göstər. */
    private function showJournals(TelegramContext $ctx): void
    {
        if (! Auth::user()->hasOperation('TRADING_VIEW')) {
            $ctx->say('Trading üçün icazən yoxdur.');

            return;
        }
        TelegramState::clear($ctx->chatId);
        $journals = TradingJournal::where('status', 'draft')
            ->orderByDesc('posting_date')->orderByDesc('created_at')->limit(20)->get();

        if ($journals->isEmpty()) {
            $ctx->say('📭 Açıq (draft) jurnal yoxdur. Web-də jurnal aç, sonra bura qayıt.');

            return;
        }
        $buttons = $journals->map(fn (TradingJournal $j) => [[
            'text' => $j->code.($j->descr ? ' · '.mb_substr($j->descr, 0, 20) : ''),
            'callback_data' => 'tr:j:'.$j->code,
        ]])->all();
        $ctx->say('💱 <b>Trading</b> — jurnal seç:', $buttons);
    }

    private function selectJournal(TelegramContext $ctx, string $code): void
    {
        $j = TradingJournal::where('code', $code)->where('status', 'draft')->first();
        if (! $j) {
            $ctx->answer('Tapılmadı və ya post olunub');
            $this->showJournals($ctx);

            return;
        }
        TelegramState::set($ctx->chatId, 'trade', 'menu', ['journal' => $code]);
        $ctx->answer();
        $this->showMenu($ctx, $j);
    }

    private function showMenu(TelegramContext $ctx, TradingJournal $j): void
    {
        $ctx->say("📔 <b>{$j->code}</b> — seç:", [
            [['text' => '🟢 Al', 'callback_data' => 'tr:buy'], ['text' => '🔴 Sat', 'callback_data' => 'tr:sell']],
            [['text' => '📊 Statistika', 'callback_data' => 'tr:stats']],
            [['text' => '◀️ Jurnallar', 'callback_data' => 'tr:start']],
        ]);
    }

    private function startEntry(TelegramContext $ctx, string $type): void
    {
        if (! Auth::user()->hasOperation('TRADING_UPDATE')) {
            $ctx->answer('İcazən yoxdur');

            return;
        }
        $state = TelegramState::get($ctx->chatId);
        $code = $state['data']['journal'] ?? null;
        if (! $code) {
            $ctx->answer();
            $this->showJournals($ctx);

            return;
        }
        TelegramState::set($ctx->chatId, 'trade', $type === 'buy' ? 'buy_manat' : 'sell_manat', ['journal' => $code, 'type' => $type]);
        $ctx->answer();
        $ctx->say($type === 'buy' ? '🟢 AL — neçə <b>manat</b> verdin?' : '🔴 SAT — neçə <b>manat</b> aldın?');
    }

    private function askConfirm(TelegramContext $ctx, array $data): void
    {
        $rate = ($data['usd'] ?? 0) > 0 ? round($data['manat'] / $data['usd'], 4) : 0;
        $label = $data['type'] === 'buy' ? '🟢 AL' : '🔴 SAT';
        $ctx->say("{$label}\n<b>{$data['manat']} ₼</b>  →  <b>{$data['usd']} USD</b>\nkurs: {$rate}\n\nTəsdiq edirsən?", [
            [['text' => '✅ Təsdiq', 'callback_data' => 'tr:confirm'], ['text' => '❌ Ləğv', 'callback_data' => 'tr:cancel']],
        ]);
    }

    private function confirmEntry(TelegramContext $ctx): void
    {
        $state = TelegramState::get($ctx->chatId);
        if (! $state || $state['step'] !== 'confirm') {
            $ctx->answer();

            return;
        }
        if (! Auth::user()->hasOperation('TRADING_UPDATE')) {
            $ctx->answer('İcazən yoxdur');

            return;
        }
        $d = $state['data'];
        $j = TradingJournal::where('code', $d['journal'])->where('status', 'draft')->first();
        if (! $j) {
            $ctx->answer('Jurnal tapılmadı');
            TelegramState::clear($ctx->chatId);

            return;
        }
        $j->entries()->create([
            'entry_type' => $d['type'],
            'manat_amount' => round((float) $d['manat'], 2),
            'usd_qty' => round((float) $d['usd'], 4),
            'descr' => null,
        ]);
        $ctx->answer('✅ Əlavə olundu');
        $ctx->clearButtons();
        TelegramState::set($ctx->chatId, 'trade', 'menu', ['journal' => $d['journal']]);
        $this->sendStats($ctx, $j, '✅ Sətir əlavə olundu.');
        $this->showMenu($ctx, $j);
    }

    private function cancel(TelegramContext $ctx): void
    {
        $state = TelegramState::get($ctx->chatId);
        $code = $state['data']['journal'] ?? null;
        $ctx->answer('Ləğv edildi');
        $ctx->clearButtons();
        $j = $code ? TradingJournal::where('code', $code)->first() : null;
        if ($j) {
            TelegramState::set($ctx->chatId, 'trade', 'menu', ['journal' => $code]);
            $this->showMenu($ctx, $j);
        } else {
            $this->showJournals($ctx);
        }
    }

    private function onStats(TelegramContext $ctx): void
    {
        $state = TelegramState::get($ctx->chatId);
        $code = $state['data']['journal'] ?? null;
        $j = $code ? TradingJournal::where('code', $code)->first() : null;
        if (! $j) {
            $ctx->answer();
            $this->showJournals($ctx);

            return;
        }
        $ctx->answer();
        $this->sendStats($ctx, $j, null);
    }

    /** Post ETMƏDƏN statistika (dry-run check). */
    private function sendStats(TelegramContext $ctx, TradingJournal $j, ?string $prefix): void
    {
        $s = app(TradingPostingService::class)->check($j);
        $msg = ($prefix ? $prefix."\n\n" : '')
            ."📊 <b>{$j->code}</b>\n"
            ."Alış: {$s['buy_manat']} ₼  ({$s['buy_usd']} $)\n"
            ."Satış: {$s['sell_manat']} ₼  ({$s['sell_usd']} $)\n"
            ."Net kassa: {$s['net_cash']} ₼\n"
            ."Mənfəət: <b>{$s['profit']} ₼</b>";
        if ($s['shortage_usd'] > 0) {
            $msg .= "\n⚠️ USD çatmır: {$s['shortage_usd']} $";
        }
        $ctx->say($msg);
    }

    private function sendBalance(TelegramContext $ctx): void
    {
        if (! Auth::user()->hasOperation('TRADING_VIEW')) {
            $ctx->say('Trading üçün icazən yoxdur.');

            return;
        }
        $open = TradingLedgerEntry::where('positive', true)->where('open', true);
        $usd = round((float) $open->clone()->sum('remain_qty'), 4);
        $cost = round((float) $open->clone()->sum(DB::raw('remain_qty * unit_amount_lcy')), 2);
        $avg = $usd > 0 ? round($cost / $usd, 4) : 0;
        $ctx->say("💰 <b>Balans</b>\nUSD: <b>{$usd} $</b>\nMaya: {$cost} ₼\nOrta kurs: {$avg}");
    }

    private function parseNum(string $text): float
    {
        $n = str_replace([',', ' '], ['.', ''], trim($text));

        return is_numeric($n) ? (float) $n : 0.0;
    }
}
