<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Measurement;
use App\Support\Translatable;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MeasurementController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(Measurement::orderBy('code')->get(['code', 'name', 'in_use']));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', 'regex:/^[A-Z][A-Z0-9_]*$/', 'unique:App\Models\Measurement,code'],
            ...Translatable::rules('name'),
        ]);

        $measurement = Measurement::create([
            'code' => $data['code'],
            'name' => Translatable::sanitize($request->input('name', [])),
        ]);

        return response()->json($this->payload($measurement), 201);
    }

    public function update(Request $request, Measurement $measurement): JsonResponse
    {
        if ($measurement->in_use) {
            return response()->json(['message' => __('messages.in_use_locked')], 422);
        }
        $request->validate(Translatable::rules('name'));
        $measurement->update(['name' => Translatable::sanitize($request->input('name', []))]);

        return response()->json($this->payload($measurement));
    }

    public function destroy(Measurement $measurement): JsonResponse
    {
        if ($measurement->in_use) {
            return response()->json(['message' => __('messages.in_use_locked')], 422);
        }
        try {
            $measurement->delete();
        } catch (QueryException $e) {
            if ($e->getCode() === '23503') {
                $measurement->update(['in_use' => true]);

                return response()->json(['message' => __('messages.in_use_locked')], 422);
            }
            throw $e;
        }

        return response()->json(['message' => __('messages.deleted')]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Measurement $measurement): array
    {
        return [
            'code' => $measurement->code,
            'name' => $measurement->name,
            'in_use' => (bool) $measurement->in_use,
        ];
    }
}
