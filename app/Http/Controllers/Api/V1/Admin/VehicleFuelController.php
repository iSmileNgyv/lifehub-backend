<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use App\Models\VehicleFuel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VehicleFuelController extends Controller
{
    /** GET /api/v1/vehicles/{vehicle}/fuel — hər doldurma + təxmini sərfiyyat (L/100km) */
    public function index(Vehicle $vehicle): JsonResponse
    {
        // Sərfiyyat üçün probeqə görə sırala (əvvəlki doldurma ilə fərq)
        $byOdo = $vehicle->fuel()->orderBy('odometer_km')->get();
        $consumption = [];
        $prev = null;
        foreach ($byOdo as $f) {
            if ($prev) {
                $dist = (float) $f->odometer_km - (float) $prev->odometer_km;
                if ($dist > 0) {
                    $consumption[$f->uid] = round((float) $f->liters / $dist * 100, 2); // L/100km
                }
            }
            $prev = $f;
        }

        $rows = $vehicle->fuel()->orderByDesc('date')->orderByDesc('odometer_km')->get();

        return response()->json([
            'data' => $rows->map(fn (VehicleFuel $f) => [
                'uid' => $f->uid,
                'date' => $f->date->toDateString(),
                'odometer_km' => $f->odometer_km,
                'liters' => $f->liters,
                'amount' => $f->amount,
                'note' => $f->note,
                'consumption' => $consumption[$f->uid] ?? null, // L/100km (ilk doldurmada null)
            ])->all(),
            'avg_consumption' => count($consumption) ? round(array_sum($consumption) / count($consumption), 2) : null,
        ]);
    }

    public function store(Request $request, Vehicle $vehicle): JsonResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'odometer_km' => ['required', 'numeric', 'min:0'], // KM (frontend mi→km çevirir)
            'liters' => ['required', 'numeric', 'gt:0'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $f = $vehicle->fuel()->create([
            'date' => $data['date'],
            'odometer_km' => round((float) $data['odometer_km'], 2),
            'liters' => round((float) $data['liters'], 2),
            'amount' => isset($data['amount']) ? round((float) $data['amount'], 2) : null,
            'note' => $data['note'] ?? null,
        ]);

        return response()->json(['uid' => $f->uid], 201);
    }

    public function destroy(Vehicle $vehicle, VehicleFuel $fuel): JsonResponse
    {
        $fuel->delete();

        return response()->json(['message' => __('messages.deleted')]);
    }
}
