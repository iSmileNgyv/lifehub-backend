<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use App\Models\VehicleReading;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VehicleReadingController extends Controller
{
    /** GET /api/v1/vehicles/{vehicle}/readings */
    public function index(Vehicle $vehicle): JsonResponse
    {
        $readings = $vehicle->readings()->orderByDesc('reading_date')->get();

        return response()->json(['data' => $readings->map(fn (VehicleReading $r) => $this->payload($r))->all()]);
    }

    /** POST /api/v1/vehicles/{vehicle}/readings — km KM-də (frontend mi→km çevirir). Eyni gün → UPSERT. */
    public function store(Request $request, Vehicle $vehicle): JsonResponse
    {
        $data = $request->validate([
            'reading_date' => ['required', 'date'],
            'km' => ['required', 'numeric', 'min:0'],
        ]);
        $km = round((float) $data['km'], 2);
        $date = $data['reading_date'];

        // Odometr monoton artan olmalıdır — bu tarixdən əvvəlkindən kiçik, sonrakından böyük ola bilməz
        $prev = VehicleReading::where('vehicle_uid', $vehicle->uid)
            ->where('reading_date', '<', $date)->orderByDesc('reading_date')->first();
        if ($prev && $km < (float) $prev->km) {
            return response()->json(['message' => 'Probeq əvvəlki oxunuşdan ('.$prev->km.') kiçik ola bilməz.'], 422);
        }
        $next = VehicleReading::where('vehicle_uid', $vehicle->uid)
            ->where('reading_date', '>', $date)->orderBy('reading_date')->first();
        if ($next && $km > (float) $next->km) {
            return response()->json(['message' => 'Probeq sonrakı oxunuşdan ('.$next->km.') böyük ola bilməz.'], 422);
        }

        $reading = VehicleReading::updateOrCreate(
            ['vehicle_uid' => $vehicle->uid, 'reading_date' => $date],
            ['km' => $km],
        );

        return response()->json($this->payload($reading), 201);
    }

    /** DELETE /api/v1/vehicles/{vehicle}/readings/{reading} */
    public function destroy(Vehicle $vehicle, VehicleReading $reading): JsonResponse
    {
        $reading->delete();

        return response()->json(['message' => __('messages.deleted')]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(VehicleReading $r): array
    {
        return [
            'uid' => $r->uid,
            'reading_date' => $r->reading_date->toDateString(),
            'km' => $r->km,
        ];
    }
}
