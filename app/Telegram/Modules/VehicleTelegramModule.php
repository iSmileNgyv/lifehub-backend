<?php

namespace App\Telegram\Modules;

use App\Models\Vehicle;
use App\Models\VehicleFuel;
use App\Models\VehicleReading;
use App\Support\PaceEstimator;
use App\Telegram\Contracts\TelegramModule;
use App\Telegram\TelegramContext;
use App\Telegram\TelegramState;
use Illuminate\Support\Facades\Auth;

/**
 * Maşın — botdan probeq oxunuşu + yanacaq əlavə + statistika. Post/jurnal yox, birbaşa qeyd.
 * Söhbət state-i ilə çoxaddımlı.
 */
class VehicleTelegramModule implements TelegramModule
{
    private const MI_TO_KM = 1.609344;

    /** İstifadəçinin daxil etdiyi vahid dəyəri → km (kanonik). */
    private function toKm(float $v, ?string $unit): float
    {
        return $unit === 'mi' ? $v * self::MI_TO_KM : $v;
    }

    /** km → istifadəçi vahidi. */
    private function toUnit(float $km, ?string $unit): float
    {
        return $unit === 'mi' ? $km / self::MI_TO_KM : $km;
    }

    private function unitLabel(?string $unit): string
    {
        return $unit === 'mi' ? 'mil' : 'km';
    }

    /** Probeqi hər iki vahiddə göstər (mi maşınında km də, əks halda təkcə km). */
    private function bothUnits(float $km, ?string $unit): string
    {
        return $unit === 'mi'
            ? round($this->toUnit($km, $unit)).' mil · '.round($km).' km'
            : round($km).' km';
    }

    public function key(): string
    {
        return 'car';
    }

    public function menuButton(): ?array
    {
        return ['text' => '🚗 Maşın', 'callback_data' => 'ca:start', 'op' => 'VEHICLE_VIEW'];
    }

    public function commands(): array
    {
        return ['car'];
    }

    public function ownsCallback(string $data): bool
    {
        return str_starts_with($data, 'ca:');
    }

    public function onCommand(TelegramContext $ctx, string $command, string $args): void
    {
        $this->showVehicles($ctx);
    }

    public function onCallback(TelegramContext $ctx, string $data): void
    {
        $parts = explode(':', $data);
        $action = $parts[1] ?? '';

        switch ($action) {
            case 'start': $ctx->answer(); $this->showVehicles($ctx); break;
            case 'v': $this->selectVehicle($ctx, $parts[2] ?? ''); break;
            case 'km': $this->askKm($ctx); break;
            case 'fuel': $this->askFuel($ctx); break;
            case 'stats': $this->stats($ctx); break;
            case 'cancel': $this->backToMenu($ctx); break;
            default: $ctx->answer();
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
        $n = $this->parseNum($text);

        if ($step === 'km') {
            if ($n <= 0) { $ctx->say('Düzgün probeq yaz (məs. 152340).'); return; }
            $this->saveReading($ctx, $data['vehicle'], $n);

            return;
        }
        if ($step === 'fuel_liters') {
            if ($n <= 0) { $ctx->say('Neçə litr? (məs. 38.5)'); return; }
            $data['liters'] = $n;
            TelegramState::set($ctx->chatId, 'car', 'fuel_odo', $data);
            $ul = $this->unitLabel(Vehicle::find($data['vehicle'])?->unit);
            $ctx->say("Probeq ({$ul})?");

            return;
        }
        if ($step === 'fuel_odo') {
            if ($n <= 0) { $ctx->say('Düzgün probeq yaz (km).'); return; }
            $data['odo'] = $n;
            TelegramState::set($ctx->chatId, 'car', 'fuel_amount', $data);
            $ctx->say('Məbləğ (₼)? Yoxdursa 0 yaz.');

            return;
        }
        if ($step === 'fuel_amount') {
            $this->saveFuel($ctx, $data, max(0.0, $n));
        }
    }

    private function showVehicles(TelegramContext $ctx): void
    {
        if (! Auth::user()->hasOperation('VEHICLE_VIEW')) {
            $ctx->say('Maşın üçün icazən yoxdur.');

            return;
        }
        TelegramState::clear($ctx->chatId);
        $vehicles = Vehicle::orderBy('name')->limit(30)->get();
        if ($vehicles->isEmpty()) {
            $ctx->say('🚗 Maşın yoxdur. Web-də maşın əlavə et.');

            return;
        }
        if ($vehicles->count() === 1) {
            $this->openVehicle($ctx, $vehicles->first());

            return;
        }
        $buttons = $vehicles->map(fn (Vehicle $v) => [['text' => $v->name.($v->plate ? " · {$v->plate}" : ''), 'callback_data' => 'ca:v:'.$v->uid]])->all();
        $ctx->say('🚗 <b>Maşın</b> seç:', $buttons);
    }

    private function selectVehicle(TelegramContext $ctx, string $uid): void
    {
        $v = Vehicle::find($uid);
        if (! $v) { $ctx->answer('Tapılmadı'); $this->showVehicles($ctx); return; }
        $ctx->answer();
        $this->openVehicle($ctx, $v);
    }

    private function openVehicle(TelegramContext $ctx, Vehicle $v): void
    {
        TelegramState::set($ctx->chatId, 'car', 'menu', ['vehicle' => $v->uid]);
        $this->showMenu($ctx, $v);
    }

    private function showMenu(TelegramContext $ctx, Vehicle $v): void
    {
        $ctx->say("🚗 <b>{$v->name}</b> — seç:", [
            [['text' => '📍 Probeq', 'callback_data' => 'ca:km'], ['text' => '⛽ Yanacaq', 'callback_data' => 'ca:fuel']],
            [['text' => '📊 Statistika', 'callback_data' => 'ca:stats']],
            [['text' => '◀️ Maşınlar', 'callback_data' => 'ca:start']],
        ]);
    }

    private function currentVehicle(TelegramContext $ctx): ?Vehicle
    {
        $uid = TelegramState::get($ctx->chatId)['data']['vehicle'] ?? null;

        return $uid ? Vehicle::find($uid) : null;
    }

    private function askKm(TelegramContext $ctx): void
    {
        if (! $this->canEdit($ctx)) { return; }
        $v = $this->currentVehicle($ctx);
        if (! $v) { $ctx->answer(); $this->showVehicles($ctx); return; }
        TelegramState::set($ctx->chatId, 'car', 'km', ['vehicle' => $v->uid]);
        $ctx->answer();
        $ctx->say('📍 Cari probeq ('.$this->unitLabel($v->unit).')?');
    }

    private function saveReading(TelegramContext $ctx, string $vehicleUid, float $input): void
    {
        $v = Vehicle::find($vehicleUid);
        if (! $v) { $ctx->say('Maşın tapılmadı'); return; }
        $km = round($this->toKm($input, $v->unit), 2); // giriş vahiddə → km saxla
        $date = now()->toDateString();
        $ul = $this->unitLabel($v->unit);

        $prev = VehicleReading::where('vehicle_uid', $v->uid)->where('reading_date', '<', $date)->orderByDesc('reading_date')->first();
        if ($prev && $km < (float) $prev->km) { $ctx->say('⚠️ Probeq əvvəlkindən ('.round($this->toUnit((float) $prev->km, $v->unit))." {$ul}) kiçik ola bilməz."); return; }
        $next = VehicleReading::where('vehicle_uid', $v->uid)->where('reading_date', '>', $date)->orderBy('reading_date')->first();
        if ($next && $km > (float) $next->km) { $ctx->say('⚠️ Probeq sonrakından ('.round($this->toUnit((float) $next->km, $v->unit))." {$ul}) böyük ola bilməz."); return; }

        VehicleReading::updateOrCreate(['vehicle_uid' => $v->uid, 'reading_date' => $date], ['km' => $km]);
        TelegramState::set($ctx->chatId, 'car', 'menu', ['vehicle' => $v->uid]);
        $ctx->say('✅ Probeq yazıldı: <b>'.round($input)." {$ul}</b> ({$date})");
        $this->showMenu($ctx, $v);
    }

    private function askFuel(TelegramContext $ctx): void
    {
        if (! $this->canEdit($ctx)) { return; }
        $v = $this->currentVehicle($ctx);
        if (! $v) { $ctx->answer(); $this->showVehicles($ctx); return; }
        TelegramState::set($ctx->chatId, 'car', 'fuel_liters', ['vehicle' => $v->uid]);
        $ctx->answer();
        $ctx->say('⛽ Neçə <b>litr</b> yanacaq vurdun?');
    }

    private function saveFuel(TelegramContext $ctx, array $data, float $amount): void
    {
        $v = Vehicle::find($data['vehicle']);
        if (! $v) { $ctx->say('Maşın tapılmadı'); return; }
        $ul = $this->unitLabel($v->unit);
        $v->fuel()->create([
            'date' => now()->toDateString(),
            'odometer_km' => round($this->toKm((float) $data['odo'], $v->unit), 2), // giriş vahiddə → km
            'liters' => round((float) $data['liters'], 2),
            'amount' => $amount > 0 ? round($amount, 2) : null,
            'note' => null,
        ]);
        // L/100km (son iki dolum)
        $extra = '';
        $fuels = VehicleFuel::where('vehicle_uid', $v->uid)->orderByDesc('odometer_km')->limit(2)->get();
        if ($fuels->count() >= 2) {
            $dist = (float) $fuels[0]->odometer_km - (float) $fuels[1]->odometer_km;
            if ($dist > 0) {
                $extra = "\nSərfiyyat: <b>".round((float) $fuels[0]->liters / $dist * 100, 2)." L/100km</b>";
            }
        }
        TelegramState::set($ctx->chatId, 'car', 'menu', ['vehicle' => $v->uid]);
        $ctx->say("✅ Yanacaq yazıldı: <b>{$data['liters']} L</b> · {$data['odo']} {$ul}".($amount > 0 ? " · {$amount} ₼" : '').$extra);
        $this->showMenu($ctx, $v);
    }

    private function stats(TelegramContext $ctx): void
    {
        $v = $this->currentVehicle($ctx);
        if (! $v) { $ctx->answer(); $this->showVehicles($ctx); return; }
        $ctx->answer();

        $pace = PaceEstimator::estimate($v->readings, $v->avg_km_per_day ? (float) $v->avg_km_per_day : null);
        $projected = PaceEstimator::projectedKm($pace['current_km'], $pace['as_of'], $pace['pace']);

        $u = $v->unit;
        $msg = "📊 <b>{$v->name}</b>\n";
        $msg .= 'Cari probeq: '.($pace['current_km'] !== null ? '<b>'.$this->bothUnits((float) $pace['current_km'], $u).'</b> ('.$pace['as_of'].')' : '—').PHP_EOL;
        if ($projected !== null) {
            $msg .= 'İndi təxmini: <b>'.$this->bothUnits((float) $projected, $u).'</b>'.PHP_EOL;
        }
        if ($pace['pace'] !== null) {
            $paceStr = $u === 'mi'
                ? round($this->toUnit((float) $pace['pace'], $u), 1).' mil/gün · '.round((float) $pace['pace'], 1).' km/gün'
                : round((float) $pace['pace'], 1).' km/gün';
            $msg .= 'Günlük tempo: '.$paceStr.PHP_EOL;
        }
        $fuels = VehicleFuel::where('vehicle_uid', $v->uid)->orderByDesc('odometer_km')->limit(2)->get();
        if ($fuels->count() >= 2) {
            $dist = (float) $fuels[0]->odometer_km - (float) $fuels[1]->odometer_km;
            if ($dist > 0) {
                $msg .= 'Son sərfiyyat: <b>'.round((float) $fuels[0]->liters / $dist * 100, 2).' L/100km</b>'.PHP_EOL;
            }
        }
        $ctx->say(trim($msg));
    }

    private function backToMenu(TelegramContext $ctx): void
    {
        $ctx->answer();
        $v = $this->currentVehicle($ctx);
        if ($v) { $this->showMenu($ctx, $v); } else { $this->showVehicles($ctx); }
    }

    private function canEdit(TelegramContext $ctx): bool
    {
        if (! Auth::user()->hasOperation('VEHICLE_UPDATE')) {
            $ctx->answer('İcazən yoxdur');

            return false;
        }

        return true;
    }

    private function parseNum(string $text): float
    {
        $n = str_replace([',', ' '], ['.', ''], trim($text));

        return is_numeric($n) ? (float) $n : 0.0;
    }
}
