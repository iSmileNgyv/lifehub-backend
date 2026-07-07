<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Vehicle;
use App\Models\VehicleService;
use App\Support\PaceEstimator;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VehicleController extends Controller
{
    /** GET /api/v1/vehicles */
    public function index(): JsonResponse
    {
        $vehicles = Vehicle::orderBy('name')->get();

        return response()->json(['data' => $vehicles->map(fn (Vehicle $v) => $this->summary($v))->all()]);
    }

    /** GET /api/v1/vehicles/{vehicle} — maşın + xidmətlərin hesablanmış statusu */
    public function show(Vehicle $vehicle): JsonResponse
    {
        $pace = PaceEstimator::estimate($vehicle->readings, $vehicle->avg_km_per_day ? (float) $vehicle->avg_km_per_day : null);
        $projected = PaceEstimator::projectedKm($pace['current_km'], $pace['as_of'], $pace['pace']);

        $services = $vehicle->services()->orderByDesc('active')->orderByDesc('installed_date')->get()
            ->map(fn (VehicleService $s) => $this->servicePayload($s, $projected, $pace['pace']))->all();

        // Xərc xülasəsi (bu ay / bu il) = məsrəf + yanacaq məbləği
        $now = now();
        $costMonth = (float) $vehicle->expenses()->whereBetween('date', [$now->copy()->startOfMonth()->toDateString(), $now->copy()->endOfMonth()->toDateString()])->sum('amount')
            + (float) $vehicle->fuel()->whereBetween('date', [$now->copy()->startOfMonth()->toDateString(), $now->copy()->endOfMonth()->toDateString()])->sum('amount');
        $costYear = (float) $vehicle->expenses()->whereBetween('date', [$now->copy()->startOfYear()->toDateString(), $now->copy()->endOfYear()->toDateString()])->sum('amount')
            + (float) $vehicle->fuel()->whereBetween('date', [$now->copy()->startOfYear()->toDateString(), $now->copy()->endOfYear()->toDateString()])->sum('amount');

        // Orta sərfiyyat (L/100km) — probeqə görə ardıcıl doldurmalardan
        $fuel = $vehicle->fuel()->orderBy('odometer_km')->get();
        $cons = [];
        $prev = null;
        foreach ($fuel as $f) {
            if ($prev) {
                $dist = (float) $f->odometer_km - (float) $prev->odometer_km;
                if ($dist > 0) {
                    $cons[] = (float) $f->liters / $dist * 100;
                }
            }
            $prev = $f;
        }

        return response()->json([
            ...$this->summary($vehicle, $pace, $projected),
            'readings' => $vehicle->readings()->orderByDesc('reading_date')->limit(60)->get()
                ->map(fn ($r) => ['uid' => $r->uid, 'reading_date' => $r->reading_date->toDateString(), 'km' => $r->km])->all(),
            'services' => $services,
            'cost_month' => round($costMonth, 2),
            'cost_year' => round($costYear, 2),
            'avg_consumption' => $cons !== [] ? round(array_sum($cons) / count($cons), 2) : null,
        ]);
    }

    /** POST /api/v1/vehicles */
    public function store(Request $request): JsonResponse
    {
        $data = $this->validateData($request);
        $vehicle = Vehicle::create($data);

        return response()->json($this->summary($vehicle), 201);
    }

    /** PATCH /api/v1/vehicles/{vehicle} */
    public function update(Request $request, Vehicle $vehicle): JsonResponse
    {
        $vehicle->update($this->validateData($request));

        return response()->json($this->summary($vehicle));
    }

    /** DELETE /api/v1/vehicles/{vehicle} */
    public function destroy(Vehicle $vehicle): JsonResponse
    {
        $vehicle->delete();

        return response()->json(['message' => __('messages.deleted')]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'plate' => ['nullable', 'string', 'max:30'],
            'unit' => ['sometimes', 'in:km,mi'],
            'avg_km_per_day' => ['nullable', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $pace
     * @return array<string, mixed>
     */
    private function summary(Vehicle $v, ?array $pace = null, ?float $projected = null): array
    {
        if ($pace === null) {
            $pace = PaceEstimator::estimate($v->readings, $v->avg_km_per_day ? (float) $v->avg_km_per_day : null);
            $projected = PaceEstimator::projectedKm($pace['current_km'], $pace['as_of'], $pace['pace']);
        }

        // Ən pis status (aktiv xidmətlər üzrə)
        $worst = 'ok';
        foreach ($v->services()->where('active', true)->get() as $s) {
            $st = $this->servicePayload($s, $projected, $pace['pace'])['status'];
            if ($st === 'overdue') { $worst = 'overdue'; break; }
            if ($st === 'soon') { $worst = 'soon'; }
        }

        return [
            'uid' => $v->uid,
            'name' => $v->name,
            'plate' => $v->plate,
            'unit' => $v->unit,
            'avg_km_per_day' => $v->avg_km_per_day,
            'note' => $v->note,
            'current_km' => $pace['current_km'],
            'projected_km' => $projected,
            'pace' => $pace['pace'],
            'readings_count' => $pace['readings'],
            'last_reading_date' => $pace['as_of'],
            'worst_status' => $worst,
        ];
    }

    /**
     * Bir xidmətin qalıq ömrü + status (km və vaxt tərəfi; hansı əvvəl bitsə).
     *
     * @return array<string, mixed>
     */
    private function servicePayload(VehicleService $s, ?float $projectedKm, ?float $pace): array
    {
        $installedKm = (float) $s->installed_km;
        $lifeKm = $s->life_km !== null ? (float) $s->life_km : null;
        $lifeMonths = $s->life_months;
        $today = now()->startOfDay();

        $kmUsedPct = null;
        $remainingKm = null;
        $daysToDueKm = null;
        if ($lifeKm !== null && $lifeKm > 0 && $projectedKm !== null) {
            $dueKm = $installedKm + $lifeKm;
            $remainingKm = round($dueKm - $projectedKm, 1);
            $kmUsedPct = ($projectedKm - $installedKm) / $lifeKm * 100;
            if ($pace !== null && $pace > 0) {
                $daysToDueKm = (int) floor($remainingKm / $pace);
            }
        }

        $timeUsedPct = null;
        $remainingDaysTime = null;
        if ($lifeMonths !== null && $lifeMonths > 0) {
            $installed = Carbon::parse($s->installed_date)->startOfDay();
            $dueDate = $installed->copy()->addMonths($lifeMonths);
            $remainingDaysTime = (int) $today->diffInDays($dueDate, false);
            $totalDays = $installed->diffInDays($dueDate);
            $usedDays = $installed->diffInDays($today, false);
            $timeUsedPct = $totalDays > 0 ? $usedDays / $totalDays * 100 : null;
        }

        $usedPct = max($kmUsedPct ?? 0, $timeUsedPct ?? 0);
        $candidates = array_filter([$daysToDueKm, $remainingDaysTime], fn ($x) => $x !== null);
        $daysLeft = $candidates !== [] ? min($candidates) : null;

        $status = $usedPct >= 100 ? 'overdue' : ($usedPct >= 75 ? 'soon' : 'ok');

        // Kateqoriya adı (item_code varsa) — kartda kiçik yazı üçün
        $categoryName = null;
        if ($s->item_code) {
            $catCode = Item::where('code', $s->item_code)->value('category_code');
            if ($catCode) {
                $categoryName = ItemCategory::where('code', $catCode)->value('name');
            }
        }

        return [
            'uid' => $s->uid,
            'item_code' => $s->item_code,
            'item_name' => $s->item_name,
            'category_name' => $categoryName,
            'installed_date' => Carbon::parse($s->installed_date)->toDateString(),
            'installed_km' => $installedKm,
            'life_km' => $lifeKm,
            'life_months' => $lifeMonths,
            'note' => $s->note,
            'active' => (bool) $s->active,
            'can_undo' => ! $s->active && $s->closed_at && $s->closed_at->gte(now()->subDay()),
            'closed_at' => $s->closed_at?->toIso8601String(),
            'used_pct' => round(min($usedPct, 999), 1),
            'remaining_km' => $remainingKm,
            'days_to_due_km' => $daysToDueKm,
            'remaining_days_time' => $remainingDaysTime,
            'days_left' => $daysLeft,
            'status' => $status,
        ];
    }
}
