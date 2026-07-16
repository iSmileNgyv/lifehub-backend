<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\ItemMeasurement;
use App\Support\Usage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ItemMeasurementController extends Controller
{
    public function index(Item $item): JsonResponse
    {
        return response()->json(
            ItemMeasurement::where('item_code', $item->code)->orderBy('measure_code')
                ->get()->map(fn (ItemMeasurement $m) => $this->payload($m))
        );
    }

    public function store(Request $request, Item $item): JsonResponse
    {
        $data = $request->validate([
            'measure_code' => [
                'required', 'string',
                Rule::exists('measurements', 'code'),
                Rule::notIn([$item->base_measure_code]),
                // Eyni vahid təkrar ola bilər (5L "ədəd" vs 8L "ədəd") → yalnız eyni (vahid+çəki) cütü qadağan.
                Rule::unique('items_measurement', 'measure_code')
                    ->where(fn ($q) => $q->where('item_code', $item->code)->where('meas_weight', $request->input('meas_weight'))),
            ],
            'meas_weight' => ['required', 'numeric', 'gt:0'],
        ]);

        $im = ItemMeasurement::create([
            'item_code' => $item->code,
            'base_measure_code' => $item->base_measure_code,
            'measure_code' => $data['measure_code'],
            'meas_weight' => $data['meas_weight'],
        ]);

        Usage::measurement($data['measure_code']);

        return response()->json($this->payload($im), 201);
    }

    public function update(Request $request, Item $item, ItemMeasurement $measurement): JsonResponse
    {
        abort_unless($measurement->item_code === $item->code, 404);

        $data = $request->validate([
            'measure_code' => [
                'sometimes', 'required', 'string',
                Rule::exists('measurements', 'code'),
                Rule::notIn([$item->base_measure_code]),
                Rule::unique('items_measurement', 'measure_code')
                    ->where(fn ($q) => $q->where('item_code', $item->code)->where('meas_weight', $request->input('meas_weight', $measurement->meas_weight)))
                    ->ignore($measurement->uid, 'uid'),
            ],
            'meas_weight' => ['sometimes', 'required', 'numeric', 'gt:0'],
        ]);

        $oldMeasure = $measurement->measure_code;

        $measurement->update([
            ...($data['measure_code'] ?? null ? ['measure_code' => $data['measure_code']] : []),
            ...(array_key_exists('meas_weight', $data) ? ['meas_weight' => $data['meas_weight']] : []),
            'base_measure_code' => $item->base_measure_code,
        ]);

        Usage::measurement($oldMeasure);
        Usage::measurement($measurement->measure_code);

        return response()->json($this->payload($measurement));
    }

    public function destroy(Item $item, ItemMeasurement $measurement): JsonResponse
    {
        abort_unless($measurement->item_code === $item->code, 404);

        if ($measurement->in_use) {
            return response()->json(['message' => __('messages.in_use_locked')], 422);
        }

        $code = $measurement->measure_code;
        $measurement->delete();
        Usage::measurement($code);

        return response()->json(['message' => __('messages.deleted')]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(ItemMeasurement $m): array
    {
        return [
            'uid' => $m->uid,
            'item_code' => $m->item_code,
            'base_measure_code' => $m->base_measure_code,
            'measure_code' => $m->measure_code,
            'meas_weight' => $m->meas_weight,
            'in_use' => (bool) $m->in_use,
        ];
    }
}
