<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\FinanceCategory;
use App\Models\FinanceLedgerEntry;
use App\Models\FinanceLedgerLine;
use App\Services\FinancePostingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Post olunmuş maliyyə ledger-i — baxış + reverse (geri qaytar) + pulsuz sahələrin (qeyd/kateqoriya) yerində redaktəsi.
 * Yalnız gəlir/xərc. Transferlər kassa ledger-indədir.
 */
class FinanceLedgerController extends Controller
{
    public function __construct(private FinancePostingService $posting) {}

    /** GET /api/v1/finance-ledger?from=&to=&cash_desk=&category_code=&entry_type= */
    public function index(Request $request): JsonResponse
    {
        $from = $request->query('from') ?: now()->startOfMonth()->toDateString();
        $to = $request->query('to') ?: now()->endOfMonth()->toDateString();

        $q = FinanceLedgerEntry::query()->with('lines')->whereBetween('posting_date', [$from, $to]);
        if ($desk = trim((string) $request->query('cash_desk', ''))) {
            $q->where('cash_desk_code', $desk);
        }
        if ($cat = trim((string) $request->query('category_code', ''))) {
            $q->where('category_code', $cat);
        }
        if ($type = trim((string) $request->query('entry_type', ''))) {
            $q->where('entry_type', $type);
        }

        $entries = $q->orderByDesc('posting_date')->orderByDesc('transaction_number')->limit(500)->get();

        return response()->json([
            'from' => $from,
            'to' => $to,
            'data' => $entries->map(fn (FinanceLedgerEntry $e) => $this->payload($e))->all(),
        ]);
    }

    /** PATCH /api/v1/finance-ledger/{financeLedger} — yalnız qeyd/kateqoriya (pul dəyişmir). */
    public function updatePosted(Request $request, FinanceLedgerEntry $financeLedger): JsonResponse
    {
        $data = $request->validate([
            'category_code' => ['nullable', 'string', Rule::exists('finance_categories', 'code')],
            'descr' => ['nullable', 'string', 'max:255'],
        ]);

        // Kateqoriya növü entry növü ilə uyğun olmalı
        if (! empty($data['category_code'])) {
            $cat = FinanceCategory::find($data['category_code']);
            if ($cat && $cat->type->value !== $financeLedger->entry_type->value) {
                throw ValidationException::withMessages(['category_code' => __('messages.finance_type_mismatch')]);
            }
        }

        $financeLedger->update([
            'category_code' => $data['category_code'] ?? null,
            'descr' => $data['descr'] ?? null,
        ]);

        return response()->json($this->payload($financeLedger->fresh('lines')));
    }

    /** POST /api/v1/finance-ledger/{financeLedger}/reverse — geri qaytar (draft-a). */
    public function reverse(Request $request, FinanceLedgerEntry $financeLedger): JsonResponse
    {
        $entry = $this->posting->reverse($financeLedger, $request->user()?->username);

        return response()->json([
            'ok' => true,
            'message' => __('messages.reversed'),
            'jnl_code' => $entry->jnl_code,
            'entry_uid' => $entry->uid,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(FinanceLedgerEntry $e): array
    {
        return [
            'uid' => $e->uid,
            'transaction_number' => $e->transaction_number,
            'posting_date' => $e->posting_date?->toDateString(),
            'entry_type' => $e->entry_type->value,
            'cash_desk_code' => $e->cash_desk_code,
            'category_code' => $e->category_code,
            'amount_lcy' => $e->amount_lcy,
            'descr' => $e->descr,
            'jnl_code' => $e->jnl_code,
            'lines' => $e->lines->sortBy('created_at')->values()->map(fn (FinanceLedgerLine $l) => [
                'uid' => $l->uid,
                'item_code' => $l->item_code,
                'item_name' => $l->item_name,
                'measure_code' => $l->measure_code,
                'meas_weight' => $l->meas_weight,
                'qty' => $l->qty,
                'unit_price' => $l->unit_price,
                'amount_lcy' => $l->amount_lcy,
            ])->all(),
        ];
    }
}
