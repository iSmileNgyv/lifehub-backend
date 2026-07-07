<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\TradingFormula;
use App\Support\FormulaEvaluator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TradingFormulaController extends Controller
{
    /** GET /api/v1/trading/formulas */
    public function index(): JsonResponse
    {
        $formulas = TradingFormula::orderByDesc('is_active')->orderByDesc('created_at')->get();

        return response()->json(['data' => $formulas->map(fn (TradingFormula $f) => $this->payload($f))->all()]);
    }

    /** POST /api/v1/trading/formulas */
    public function store(Request $request): JsonResponse
    {
        $data = $this->validateData($request);

        $formula = TradingFormula::create([
            'name' => $data['name'],
            'tiers' => $data['tiers'],
            'is_active' => false,
        ]);

        if ($request->boolean('is_active')) {
            $this->activateOnly($formula);
        }

        return response()->json($this->payload($formula->refresh()), 201);
    }

    /** PATCH /api/v1/trading/formulas/{formula} */
    public function update(Request $request, TradingFormula $formula): JsonResponse
    {
        $data = $this->validateData($request);

        $formula->update(['name' => $data['name'], 'tiers' => $data['tiers']]);

        if ($request->has('is_active')) {
            $request->boolean('is_active') ? $this->activateOnly($formula) : $formula->update(['is_active' => false]);
        }

        return response()->json($this->payload($formula->refresh()));
    }

    /** DELETE /api/v1/trading/formulas/{formula} */
    public function destroy(TradingFormula $formula): JsonResponse
    {
        $formula->delete();

        return response()->json(['message' => __('messages.deleted')]);
    }

    /** PUT /api/v1/trading/formulas/{formula}/activate — bunu aktiv et, qalanları söndür */
    public function activate(TradingFormula $formula): JsonResponse
    {
        $this->activateOnly($formula);

        return response()->json($this->payload($formula->refresh()));
    }

    /** POST /api/v1/trading/formulas/compute — aktiv formula ilə {amount} → USD */
    public function compute(Request $request): JsonResponse
    {
        $data = $request->validate(['amount' => ['required', 'numeric', 'min:0']]);

        $active = TradingFormula::where('is_active', true)->first();
        if (! $active) {
            return response()->json(['message' => 'Aktiv formula yoxdur.'], 422);
        }

        try {
            $r = FormulaEvaluator::apply($active->tiers, (float) $data['amount']);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['tier' => $r['tier'], 'result' => $r['result']]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateData(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'tiers' => ['required', 'array', 'min:1'],
            'tiers.*.from' => ['nullable', 'numeric'],
            'tiers.*.to' => ['nullable', 'numeric'],
            'tiers.*.expr' => ['required', 'string', 'max:200'],
        ]);

        // İfadələri yoxla — sintaksis düzgündürmü (x=1 ilə sınaq)
        foreach ($data['tiers'] as $i => $tier) {
            try {
                FormulaEvaluator::evaluate($tier['expr'], 1.0);
            } catch (RuntimeException $e) {
                abort(response()->json([
                    'message' => "Pillə #".($i + 1).": {$e->getMessage()}",
                ], 422));
            }
        }

        return $data;
    }

    private function activateOnly(TradingFormula $formula): void
    {
        DB::transaction(function () use ($formula) {
            TradingFormula::where('is_active', true)->where('uid', '!=', $formula->uid)->update(['is_active' => false]);
            $formula->update(['is_active' => true]);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(TradingFormula $f): array
    {
        return [
            'uid' => $f->uid,
            'name' => $f->name,
            'tiers' => $f->tiers,
            'is_active' => (bool) $f->is_active,
            'created_at' => $f->created_at?->toIso8601String(),
        ];
    }
}
