<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Vehicle;
use App\Models\VehicleExpense;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VehicleExpenseController extends Controller
{
    /** GET /api/v1/vehicles/{vehicle}/expenses */
    public function index(Vehicle $vehicle): JsonResponse
    {
        $rows = $vehicle->expenses()->orderByDesc('date')->orderByDesc('created_at')->get();

        return response()->json(['data' => $rows->map(fn (VehicleExpense $e) => [
            'uid' => $e->uid, 'date' => $e->date->toDateString(), 'title' => $e->title, 'amount' => $e->amount, 'note' => $e->note,
        ])->all()]);
    }

    public function store(Request $request, Vehicle $vehicle): JsonResponse
    {
        $data = $request->validate([
            'date' => ['required', 'date'],
            'title' => ['required', 'string', 'max:120'],
            'amount' => ['required', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $e = $vehicle->expenses()->create([
            'date' => $data['date'], 'title' => $data['title'],
            'amount' => round((float) $data['amount'], 2), 'note' => $data['note'] ?? null,
        ]);

        return response()->json(['uid' => $e->uid], 201);
    }

    public function destroy(Vehicle $vehicle, VehicleExpense $expense): JsonResponse
    {
        $expense->delete();

        return response()->json(['message' => __('messages.deleted')]);
    }
}
