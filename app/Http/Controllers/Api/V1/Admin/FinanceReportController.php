<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\CashDesk;
use App\Models\CashLedgerEntry;
use App\Models\FinanceBudget;
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

        // Əvvəlki eyni uzunluqlu dövr (müqayisə üçün)
        [$pf, $pt] = $this->prevRange($from, $to);
        $prev = FinanceLedgerEntry::query()
            ->whereBetween('posting_date', [$pf, $pt])
            ->selectRaw('entry_type::text as etype, SUM(amount_lcy) as total')
            ->groupBy('etype')->pluck('total', 'etype');
        $prevIncome = (float) ($prev['income'] ?? 0);
        $prevExpense = (float) ($prev['expense'] ?? 0);

        return response()->json([
            'from' => $from, 'to' => $to,
            'income' => round($income, 2),
            'expense' => round($expense, 2),
            'net' => round($income - $expense, 2),
            'prev_income' => round($prevIncome, 2),
            'prev_expense' => round($prevExpense, 2),
            'prev_net' => round($prevIncome - $prevExpense, 2),
            'rows' => $rows->map(fn ($r) => [
                'entry_type' => $r->entry_type->value,
                'category_code' => $r->category_code,
                'total' => (float) $r->total,
                'cnt' => (int) $r->cnt,
            ])->values(),
        ]);
    }

    /** GET /api/v1/finance-reports/items?from=&to= — məhsul + qiymət analitikası (nəyə nə qədər + vahid qiymət) */
    public function items(Request $request): JsonResponse
    {
        [$from, $to] = $this->range($request);

        // Variant üzrə qrup (item + vahid + çəki) — hamısı SQL-də aqreqasiya olunur (perf + düzgün "top").
        // Qiymət analitikası: orta (çəki üzrə) / min / max / ilk / son vahid qiymət + dəyişmə %.
        $rows = FinanceLedgerLine::query()
            ->whereBetween('posting_date', [$from, $to])
            ->selectRaw(<<<'SQL'
                item_code,
                measure_code,
                meas_weight,
                (array_agg(item_name ORDER BY posting_date DESC, created_at DESC))[1] as item_name,
                SUM(qty) as qty,
                SUM(amount_lcy) as total,
                COUNT(*) as cnt,
                MIN(unit_price) as min_price,
                MAX(unit_price) as max_price,
                (array_agg(unit_price ORDER BY posting_date ASC, created_at ASC))[1] as first_price,
                (array_agg(unit_price ORDER BY posting_date DESC, created_at DESC))[1] as last_price,
                CASE WHEN SUM(qty) > 0 THEN SUM(amount_lcy) / SUM(qty) ELSE 0 END as avg_price
            SQL)
            ->groupBy('item_code', 'measure_code', 'meas_weight')
            ->orderByRaw('SUM(amount_lcy) DESC')
            ->get()
            ->map(function ($r) {
                $first = (float) $r->first_price;
                $last = (float) $r->last_price;

                return [
                    'item_code' => $r->item_code,
                    'item_name' => is_string($r->item_name) ? json_decode($r->item_name, true) : $r->item_name,
                    'measure_code' => $r->measure_code,
                    'meas_weight' => $r->meas_weight !== null ? (float) $r->meas_weight : null,
                    'qty' => round((float) $r->qty, 4),
                    'total' => round((float) $r->total, 2),
                    'cnt' => (int) $r->cnt,
                    'avg_price' => round((float) $r->avg_price, 2),
                    'min_price' => round((float) $r->min_price, 2),
                    'max_price' => round((float) $r->max_price, 2),
                    'first_price' => round($first, 2),
                    'last_price' => round($last, 2),
                    'price_change_pct' => $first > 0 ? round((($last - $first) / $first) * 100, 1) : null,
                ];
            });

        [$pf, $pt] = $this->prevRange($from, $to);
        $prevTotal = (float) FinanceLedgerLine::query()->whereBetween('posting_date', [$pf, $pt])->sum('amount_lcy');

        return response()->json([
            'from' => $from, 'to' => $to,
            'rows' => $rows->values(),
            'total' => round((float) $rows->sum('total'), 2),
            'prev_total' => round($prevTotal, 2),
        ]);
    }

    /** GET /api/v1/finance-reports/trend?months=6 — son N ay üzrə gəlir/xərc (dashboard chart). */
    public function trend(Request $request): JsonResponse
    {
        $months = min(24, max(1, (int) $request->query('months', 6)));
        $start = now()->startOfMonth()->subMonths($months - 1);

        $rows = FinanceLedgerEntry::query()
            ->where('posting_date', '>=', $start->toDateString())
            ->selectRaw("to_char(posting_date, 'YYYY-MM') as ym, entry_type::text as etype, SUM(amount_lcy) as total")
            ->groupBy('ym', 'etype')
            ->get();

        // Boş ay-larla birlikdə tam interval qur
        $buckets = [];
        for ($i = 0; $i < $months; $i++) {
            $m = $start->copy()->addMonths($i)->format('Y-m');
            $buckets[$m] = ['month' => $m, 'income' => 0.0, 'expense' => 0.0];
        }
        foreach ($rows as $r) {
            if (isset($buckets[$r->ym]) && in_array($r->etype, ['income', 'expense'], true)) {
                $buckets[$r->ym][$r->etype] = round((float) $r->total, 2);
            }
        }

        return response()->json(['months' => array_values($buckets)]);
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

        // in/out cəmini aqreqasiya ilə hesabla (entries 500 ilə limitlidir → cəm ondan alınmamalıdır)
        $sums = fn (string $f, string $t) => CashLedgerEntry::query()
            ->whereBetween('posting_date', [$f, $t])
            ->when($deskCode !== '', fn ($x) => $x->where('cash_desk_code', $deskCode))
            ->selectRaw('entry_type::text as etype, SUM(amount_lcy) as total')
            ->groupBy('etype')->pluck('total', 'etype');

        $cur = $sums($from, $to);
        $in = (float) ($cur['cash_in'] ?? 0);
        $out = (float) ($cur['cash_out'] ?? 0);

        [$pf, $pt] = $this->prevRange($from, $to);
        $pr = $sums($pf, $pt);
        $prevIn = (float) ($pr['cash_in'] ?? 0);
        $prevOut = (float) ($pr['cash_out'] ?? 0);

        // Günlük axın seriyası (SQL aqreqasiya) — zamanla kassa hərəkəti qrafiki üçün
        $flowRows = CashLedgerEntry::query()
            ->whereBetween('posting_date', [$from, $to])
            ->when($deskCode !== '', fn ($x) => $x->where('cash_desk_code', $deskCode))
            ->selectRaw("posting_date::text as d, entry_type::text as etype, SUM(amount_lcy) as total")
            ->groupBy('d', 'etype')->get();
        $flowMap = [];
        foreach ($flowRows as $r) {
            $flowMap[$r->d] ??= ['date' => $r->d, 'in' => 0.0, 'out' => 0.0];
            $flowMap[$r->d][$r->etype === 'cash_in' ? 'in' : 'out'] = round((float) $r->total, 2);
        }
        ksort($flowMap);

        // Kassa hesabatı (bank çıxarışı): dövr üçün açılış → hərəkət → bağlanış (hər desk).
        $signed = "SUM(CASE WHEN entry_type = 'cash_in' THEN amount_lcy ELSE -amount_lcy END)";
        $opening = CashLedgerEntry::query()->where('posting_date', '<', $from)
            ->selectRaw("cash_desk_code, {$signed} as bal")->groupBy('cash_desk_code')->pluck('bal', 'cash_desk_code');
        $pIn = CashLedgerEntry::query()->whereBetween('posting_date', [$from, $to])->where('entry_type', 'cash_in')
            ->selectRaw('cash_desk_code, SUM(amount_lcy) as t')->groupBy('cash_desk_code')->pluck('t', 'cash_desk_code');
        $pOut = CashLedgerEntry::query()->whereBetween('posting_date', [$from, $to])->where('entry_type', 'cash_out')
            ->selectRaw('cash_desk_code, SUM(amount_lcy) as t')->groupBy('cash_desk_code')->pluck('t', 'cash_desk_code');

        return response()->json([
            'from' => $from, 'to' => $to,
            'in' => round($in, 2),
            'out' => round($out, 2),
            'net' => round($in - $out, 2),
            'prev_in' => round($prevIn, 2),
            'prev_out' => round($prevOut, 2),
            'prev_net' => round($prevIn - $prevOut, 2),
            'flow' => array_values($flowMap),
            'desks' => CashDesk::orderBy('code')->get()->map(function (CashDesk $d) use ($opening, $pIn, $pOut) {
                $op = (float) ($opening[$d->code] ?? 0);
                $din = (float) ($pIn[$d->code] ?? 0);
                $dout = (float) ($pOut[$d->code] ?? 0);

                return [
                    'code' => $d->code, 'description' => $d->description, 'balance_lcy' => $d->balance_lcy,
                    'opening' => round($op, 2), 'period_in' => round($din, 2), 'period_out' => round($dout, 2), 'closing' => round($op + $din - $dout, 2),
                ];
            })->values(),
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

    /** GET /api/v1/finance-reports/budget?from=&to= — büdcə vs faktiki (aylıq limit seçilmiş dövrə proporsional) */
    public function budget(Request $request): JsonResponse
    {
        [$from, $to] = $this->range($request);
        $f = \Illuminate\Support\Carbon::parse($from);
        $t = \Illuminate\Support\Carbon::parse($to);
        $days = $f->diffInDays($t) + 1;
        $factor = $days / 30.4375; // aylıq limit → dövr (orta ay uzunluğu)

        // Faktiki: xərc kateqoriya üzrə + ümumi xərc + ümumi gəlir
        $exp = FinanceLedgerEntry::query()->whereBetween('posting_date', [$from, $to])->where('entry_type', 'expense')
            ->selectRaw('category_code, SUM(amount_lcy) as total')->groupBy('category_code')->get();
        $expByCat = [];
        $expTotal = 0.0;
        foreach ($exp as $r) {
            $expByCat[$r->category_code ?? ''] = (float) $r->total;
            $expTotal += (float) $r->total;
        }
        $incTotal = (float) FinanceLedgerEntry::query()->whereBetween('posting_date', [$from, $to])->where('entry_type', 'income')->sum('amount_lcy');

        $overall = null;
        $income = null;
        $cats = [];
        foreach (FinanceBudget::all() as $b) {
            $monthly = (float) $b->amount_lcy;
            $prorated = round($monthly * $factor, 2);
            if ($b->kind === 'overall_expense') {
                $overall = ['monthly' => $monthly, 'prorated' => $prorated, 'actual' => round($expTotal, 2)];
            } elseif ($b->kind === 'income_target') {
                $income = ['monthly' => $monthly, 'prorated' => $prorated, 'actual' => round($incTotal, 2)];
            } else {
                $cats[] = [
                    'category_code' => $b->category_code,
                    'monthly' => $monthly,
                    'prorated' => $prorated,
                    'actual' => round($expByCat[$b->category_code] ?? 0, 2),
                ];
            }
        }
        usort($cats, fn ($a, $b) => $b['actual'] <=> $a['actual']);

        return response()->json([
            'from' => $from, 'to' => $to,
            'factor' => round($factor, 3),
            'overall' => $overall,
            'income' => $income,
            'categories' => $cats,
        ]);
    }

    /** GET /api/v1/finance-reports/entries?from=&to=&type=&category= — kateqoriya drill-down (arxadakı əməliyyatlar) */
    public function entries(Request $request): JsonResponse
    {
        [$from, $to] = $this->range($request);
        $type = $request->query('type'); // income | expense

        $q = FinanceLedgerEntry::query()->whereBetween('posting_date', [$from, $to]);
        if (in_array($type, ['income', 'expense'], true)) {
            $q->where('entry_type', $type);
        }
        if ($request->has('category')) {
            $cat = (string) $request->query('category', '');
            // '' və ya '__NONE__' → kateqoriyasız (frontend qs boş dəyəri atdığı üçün sentinel)
            ($cat === '' || $cat === '__NONE__') ? $q->whereNull('category_code') : $q->where('category_code', $cat);
        }
        $rows = $q->orderByDesc('posting_date')->orderByDesc('transaction_number')->limit(500)->get();

        return response()->json([
            'rows' => $rows->map(fn (FinanceLedgerEntry $e) => [
                'uid' => $e->uid,
                'posting_date' => $e->posting_date?->toDateString(),
                'entry_type' => $e->entry_type->value,
                'category_code' => $e->category_code,
                'cash_desk_code' => $e->cash_desk_code,
                'amount_lcy' => $e->amount_lcy,
                'descr' => $e->descr,
                'transaction_number' => $e->transaction_number,
            ])->values(),
            'total' => round((float) $rows->sum('amount_lcy'), 2),
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

    /**
     * Əvvəlki eyni uzunluqlu dövr: [from-1gün] geri, dövr uzunluğu qədər.
     *
     * @return array{0: string, 1: string}
     */
    private function prevRange(string $from, string $to): array
    {
        $f = \Illuminate\Support\Carbon::parse($from);
        $t = \Illuminate\Support\Carbon::parse($to);
        $days = $f->diffInDays($t) + 1;
        $pt = $f->copy()->subDay();
        $pf = $pt->copy()->subDays($days - 1);

        return [$pf->toDateString(), $pt->toDateString()];
    }
}
