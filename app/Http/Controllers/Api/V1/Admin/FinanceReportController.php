<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\CashDesk;
use App\Models\CashLedgerEntry;
use App\Models\FinanceLedgerEntry;
use App\Models\FinanceLedgerLine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FinanceReportController extends Controller
{
    /** GET /api/v1/finance-reports/summary?from=&to= — kateqoriya üzrə gəlir/xərc */
    public function summary(Request $request): JsonResponse
    {
        [$from, $to] = $this->range($request);

        $rows = FinanceLedgerEntry::query()
            ->whereBetween('posting_date', [$from, $to])
            ->selectRaw('entry_type, category_code, SUM(amount_lcy) as total, COUNT(*) as cnt')
            ->groupBy('entry_type', 'category_code')
            ->get();

        $income = (float) $rows->where('entry_type', 'income')->sum('total');
        $expense = (float) $rows->where('entry_type', 'expense')->sum('total');

        return response()->json([
            'from' => $from, 'to' => $to,
            'income' => round($income, 2),
            'expense' => round($expense, 2),
            'net' => round($income - $expense, 2),
            'rows' => $rows->map(fn ($r) => [
                'entry_type' => $r->entry_type->value,
                'category_code' => $r->category_code,
                'total' => (float) $r->total,
                'cnt' => (int) $r->cnt,
            ])->values(),
        ]);
    }

    /** GET /api/v1/finance-reports/items?from=&to= — məhsul üzrə (nəyə nə qədər) */
    public function items(Request $request): JsonResponse
    {
        [$from, $to] = $this->range($request);

        $rows = FinanceLedgerLine::query()
            ->whereBetween('posting_date', [$from, $to])
            ->get()
            ->groupBy('item_code')
            ->map(fn ($g) => [
                'item_code' => $g->first()->item_code,
                'item_name' => $g->first()->item_name,
                'measure_code' => $g->first()->measure_code,
                'qty' => round((float) $g->sum('qty'), 4),
                'total' => round((float) $g->sum('amount_lcy'), 2),
            ])
            ->sortByDesc('total')
            ->values();

        return response()->json(['from' => $from, 'to' => $to, 'rows' => $rows, 'total' => round((float) $rows->sum('total'), 2)]);
    }

    /** GET /api/v1/finance-reports/cash?from=&to=&cash_desk= — kassa pul hərəkəti + balanslar */
    public function cash(Request $request): JsonResponse
    {
        [$from, $to] = $this->range($request);
        $deskCode = trim((string) $request->query('cash_desk', ''));

        $q = CashLedgerEntry::query()->whereBetween('posting_date', [$from, $to]);
        if ($deskCode !== '') {
            $q->where('cash_desk_code', $deskCode);
        }
        $entries = $q->orderByDesc('posting_date')->orderByDesc('transaction_number')->limit(500)->get();

        $in = (float) $entries->where('entry_type', 'cash_in')->sum('amount_lcy');
        $out = (float) $entries->where('entry_type', 'cash_out')->sum('amount_lcy');

        return response()->json([
            'from' => $from, 'to' => $to,
            'in' => round($in, 2),
            'out' => round($out, 2),
            'net' => round($in - $out, 2),
            'desks' => CashDesk::orderBy('code')->get()->map(fn (CashDesk $d) => [
                'code' => $d->code, 'description' => $d->description, 'balance_lcy' => $d->balance_lcy,
            ])->values(),
            'entries' => $entries->map(fn (CashLedgerEntry $e) => [
                'uid' => $e->uid,
                'posting_date' => $e->posting_date?->toDateString(),
                'cash_desk_code' => $e->cash_desk_code,
                'entry_type' => $e->entry_type->value,
                'amount_lcy' => $e->amount_lcy,
                'descr' => $e->descr,
                'doc_no' => $e->doc_no,
            ])->values(),
        ]);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function range(Request $request): array
    {
        $from = $request->query('from') ?: now()->startOfMonth()->toDateString();
        $to = $request->query('to') ?: now()->endOfMonth()->toDateString();

        return [$from, $to];
    }
}
