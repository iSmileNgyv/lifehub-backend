<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\CashDesk;
use App\Models\NumberSeries;
use App\Models\TradingJournal;
use App\Models\TradingJournalEntry;
use App\Models\TradingLedgerEntry;
use App\Services\TradingPostingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use RuntimeException;

class TradingJournalController extends Controller
{
    /** GET /api/v1/trading/balance — cari USD qalığı (açıq FIFO təbəqələri) */
    public function balance(): JsonResponse
    {
        $open = TradingLedgerEntry::where('positive', true)->where('open', true);
        $usd = (float) $open->clone()->sum('remain_qty');
        $cost = (float) $open->clone()->sum(DB::raw('remain_qty * unit_amount_lcy'));

        return response()->json([
            'usd' => round($usd, 4),                       // nə qədər USD qalıb
            'cost_lcy' => round($cost, 2),                 // qalığın maya dəyəri (manat)
            'avg_cost' => $usd > 0 ? round($cost / $usd, 4) : 0, // orta alış kursu
        ]);
    }

    /** GET /api/v1/trading/stats?month=YYYY-MM — ayın statistikası (post olunmuş jurnallar) */
    public function stats(Request $request): JsonResponse
    {
        $month = (string) $request->query('month', '');
        if (! preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = now()->format('Y-m');
        }
        $start = $month.'-01';
        $end = date('Y-m-t', strtotime($start));

        $codes = TradingJournal::where('status', 'posted')
            ->whereBetween('posting_date', [$start, $end])->pluck('code');

        $revenue = (float) TradingJournalEntry::whereIn('journal_code', $codes)->where('entry_type', 'sell')->sum('manat_amount');
        $buy = (float) TradingJournalEntry::whereIn('journal_code', $codes)->where('entry_type', 'buy')->sum('manat_amount');
        $cogs = (float) TradingLedgerEntry::whereIn('journal_code', $codes)->where('entry_type', 'sell')->sum('amount_lcy');

        return response()->json([
            'month' => $month,
            'revenue' => round($revenue, 2),   // toplam gələn pul (satış)
            'buy' => round($buy, 2),            // toplam alış
            'cogs' => round($cogs, 2),
            'profit' => round($revenue - $cogs, 2), // xalis mənfəət
            'journals' => $codes->count(),
        ]);
    }

    /** GET /api/v1/trading/journals */
    public function index(): JsonResponse
    {
        $journals = TradingJournal::withCount('entries')->orderByDesc('posting_date')->orderByDesc('created_at')->get();

        return response()->json(['data' => $journals->map(fn (TradingJournal $j) => $this->payload($j))->all()]);
    }

    /** GET /api/v1/trading/journals/{journal} — başlıq + sətirlər + xülasə */
    public function show(TradingJournal $journal): JsonResponse
    {
        $journal->loadCount('entries');
        $entries = $journal->entries()->orderBy('created_at')->get();

        return response()->json([
            ...$this->payload($journal),
            'entries' => $entries->map(fn ($e) => [
                'uid' => $e->uid,
                'entry_type' => $e->entry_type->value,
                'manat_amount' => $e->manat_amount,
                'usd_qty' => $e->usd_qty,
                'descr' => $e->descr,
            ])->all(),
        ]);
    }

    /** POST /api/v1/trading/journals */
    public function store(Request $request): JsonResponse
    {
        $data = $this->validateData($request);

        $journal = TradingJournal::create([
            'code' => NumberSeries::generateNext('TRADING'),
            'cash_desk_code' => $data['cash_desk_code'] ?? null,
            'descr' => $data['descr'] ?? null,
            'posting_date' => $data['posting_date'],
            'resp_person' => $request->user()?->username,
            'status' => 'draft',
        ]);

        return response()->json($this->payload($journal->loadCount('entries')), 201);
    }

    /** PATCH /api/v1/trading/journals/{journal} — yalnız draft */
    public function update(Request $request, TradingJournal $journal): JsonResponse
    {
        if ($journal->status !== 'draft') {
            return response()->json(['message' => 'Post olunmuş jurnal dəyişdirilə bilməz.'], 422);
        }

        $data = $this->validateData($request);
        $journal->update([
            'cash_desk_code' => $data['cash_desk_code'] ?? null,
            'descr' => $data['descr'] ?? null,
            'posting_date' => $data['posting_date'],
        ]);

        return response()->json($this->payload($journal->loadCount('entries')));
    }

    /** DELETE /api/v1/trading/journals/{journal} — yalnız draft */
    public function destroy(TradingJournal $journal): JsonResponse
    {
        if ($journal->status !== 'draft') {
            return response()->json(['message' => 'Post olunmuş jurnal silinə bilməz.'], 422);
        }

        $journal->delete();

        return response()->json(['message' => __('messages.deleted')]);
    }

    /** POST /api/v1/trading/journals/{journal}/post — FIFO settle + net kassa + mənfəət */
    public function post(TradingJournal $journal, TradingPostingService $service): JsonResponse
    {
        try {
            $result = $service->post($journal);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => __('messages.saved'), ...$result]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateData(Request $request): array
    {
        return $request->validate([
            'cash_desk_code' => ['nullable', 'string', Rule::exists('App\Models\CashDesk', 'code')],
            'descr' => ['nullable', 'string', 'max:200'],
            'posting_date' => ['required', 'date'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(TradingJournal $j): array
    {
        $buyManat = (float) $j->entries()->where('entry_type', 'buy')->sum('manat_amount');
        $sellManat = (float) $j->entries()->where('entry_type', 'sell')->sum('manat_amount');
        $usdBought = (float) $j->entries()->where('entry_type', 'buy')->sum('usd_qty');
        $usdSold = (float) $j->entries()->where('entry_type', 'sell')->sum('usd_qty');
        // COGS/mənfəət — post olunubsa ledger-dən (satış çıxışlarının maya cəmi)
        $cogs = (float) TradingLedgerEntry::where('journal_code', $j->code)->where('entry_type', 'sell')->sum('amount_lcy');

        return [
            'code' => $j->code,
            'cash_desk_code' => $j->cash_desk_code,
            'cash_desk_name' => $j->cash_desk_code ? CashDesk::find($j->cash_desk_code)?->description : null,
            'descr' => $j->descr,
            'posting_date' => $j->posting_date?->toDateString(),
            'status' => $j->status,
            'posted_at' => $j->posted_at?->toIso8601String(),
            'resp_person' => $j->resp_person,
            'entries_count' => $j->entries_count ?? $j->entries()->count(),
            'buy_manat' => round($buyManat, 2),
            'sell_manat' => round($sellManat, 2),
            'usd_bought' => round($usdBought, 4),
            'usd_sold' => round($usdSold, 4),
            'net_cash' => round($sellManat - $buyManat, 2),
            'cogs' => round($cogs, 2),
            'profit' => round($sellManat - $cogs, 2),
        ];
    }
}
