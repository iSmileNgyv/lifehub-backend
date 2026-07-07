<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\Vehicle;
use App\Models\VehicleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VehicleServiceController extends Controller
{
    /** POST /api/v1/vehicles/{vehicle}/services — dəyişmə/xidmət qeydi (km KM-də) */
    public function store(Request $request, Vehicle $vehicle): JsonResponse
    {
        $data = $this->validateData($request);

        // Eyni hissənin köhnə aktiv qeydini bağla (dəyişildi)
        if ($data['item_code'] ?? null) {
            VehicleService::where('vehicle_uid', $vehicle->uid)
                ->where('item_code', $data['item_code'])->where('active', true)
                ->update(['active' => false, 'closed_at' => now()]);
        }

        $service = $vehicle->services()->create([
            'item_code' => $data['item_code'] ?? null,
            'item_name' => ($data['item_code'] ?? null) ? Item::find($data['item_code'])?->name : ($data['item_name'] ?? null),
            'installed_date' => $data['installed_date'],
            'installed_km' => round((float) $data['installed_km'], 2),
            'life_km' => isset($data['life_km']) ? round((float) $data['life_km'], 2) : null,
            'life_months' => $data['life_months'] ?? null,
            'note' => $data['note'] ?? null,
            'active' => true,
        ]);

        // Xidmət xərci (hissə + usta haqqı və s.) → məsrəflərə düşür
        if (! empty($data['amount']) && (float) $data['amount'] > 0) {
            $nameArr = $service->item_name;                       // overloaded property → lokal köçürmə
            $name = is_array($nameArr) && $nameArr !== [] ? (string) reset($nameArr) : null;
            $vehicle->expenses()->create([
                'date' => $data['installed_date'],
                'title' => $name ?: ($service->item_code ?? 'Xidmət'),
                'amount' => round((float) $data['amount'], 2),
                'note' => 'Xidmət',
            ]);
        }

        return response()->json(['uid' => $service->uid], 201);
    }

    /** PATCH /api/v1/vehicles/{vehicle}/services/{service} */
    public function update(Request $request, Vehicle $vehicle, VehicleService $service): JsonResponse
    {
        $data = $this->validateData($request);

        $service->update([
            'item_code' => $data['item_code'] ?? null,
            'item_name' => ($data['item_code'] ?? null) ? Item::find($data['item_code'])?->name : ($data['item_name'] ?? $service->item_name),
            'installed_date' => $data['installed_date'],
            'installed_km' => round((float) $data['installed_km'], 2),
            'life_km' => isset($data['life_km']) ? round((float) $data['life_km'], 2) : null,
            'life_months' => $data['life_months'] ?? null,
            'note' => $data['note'] ?? null,
        ]);

        return response()->json(['uid' => $service->uid]);
    }

    /** PUT /api/v1/vehicles/{vehicle}/services/{service}/close — dəyişildi (aktivdən çıxar) */
    public function close(Vehicle $vehicle, VehicleService $service): JsonResponse
    {
        $service->update(['active' => false, 'closed_at' => now()]);

        return response()->json(['message' => __('messages.saved')]);
    }

    /** PUT /api/v1/vehicles/{vehicle}/services/{service}/reactivate — geri al (24 saat ərzində) */
    public function reactivate(Vehicle $vehicle, VehicleService $service): JsonResponse
    {
        if ($service->active) {
            return response()->json(['message' => 'Xidmət onsuz da aktivdir.'], 422);
        }
        if ($service->closed_at && $service->closed_at->lt(now()->subDay())) {
            return response()->json(['message' => '24 saat keçib — geri alına bilməz.'], 422);
        }

        $service->update(['active' => true, 'closed_at' => null]);

        return response()->json(['message' => __('messages.saved')]);
    }

    /** DELETE /api/v1/vehicles/{vehicle}/services/{service} */
    public function destroy(Vehicle $vehicle, VehicleService $service): JsonResponse
    {
        $service->delete();

        return response()->json(['message' => __('messages.deleted')]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateData(Request $request): array
    {
        return $request->validate([
            'item_code' => ['nullable', 'string', Rule::exists('items', 'code')],
            'item_name' => ['nullable', 'array'],
            'installed_date' => ['required', 'date'],
            'installed_km' => ['required', 'numeric', 'min:0'],
            'life_km' => ['nullable', 'numeric', 'gt:0'],
            'life_months' => ['nullable', 'integer', 'gt:0'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
    }
}
