<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\FinanceBudget;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinanceBudgetController extends Controller
{
    /** GET /api/v1/finance-budgets — bütün büdcə/hədəflər (owner-scoped) */
    public function index(): JsonResponse
    {
        $rows = FinanceBudget::orderBy('kind')->get();

        return response()->json(['data' => $rows->map(fn (FinanceBudget $b) => $this->payload($b))->values()]);
    }

    /** POST /api/v1/finance-budgets — büdcəni təyin et/yenilə (amount 0 → sil). (kind, category_code) üzrə upsert. */
    public function upsert(Request $request): JsonResponse
    {
        $data = $request->validate([
            'kind' => ['required', 'in:category_expense,overall_expense,income_target'],
            'category_code' => ['nullable', 'string', 'max:60'],
            'amount' => ['required', 'numeric', 'min:0'],
        ]);

        $cat = $data['kind'] === 'category_expense' ? ($data['category_code'] ?: null) : null;
        if ($data['kind'] === 'category_expense' && $cat === null) {
            abort(response()->json(['message' => 'Kateqoriya lazımdır.'], 422));
        }

        // (kind, category_code) üzrə mövcud (owner scope avtomatik)
        $q = FinanceBudget::where('kind', $data['kind']);
        $cat === null ? $q->whereNull('category_code') : $q->where('category_code', $cat);
        $existing = $q->first();

        // 0 → sil
        if ((float) $data['amount'] <= 0) {
            $existing?->delete();

            return response()->json(['deleted' => true]);
        }

        $b = $existing ?? new FinanceBudget(['kind' => $data['kind'], 'category_code' => $cat]);
        $b->amount_lcy = $data['amount'];
        $b->save();

        return response()->json($this->payload($b), $existing ? 200 : 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(FinanceBudget $b): array
    {
        return [
            'uid' => $b->uid,
            'kind' => $b->kind,
            'category_code' => $b->category_code,
            'amount_lcy' => $b->amount_lcy,
        ];
    }
}
