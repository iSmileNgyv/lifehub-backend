<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\FinanceCategory;
use App\Models\FinanceJournal;
use App\Models\FinanceJournalEntry;
use App\Models\FinanceJournalLine;
use App\Models\Item;
use App\Services\FinancePostingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class FinanceJournalController extends Controller
{
    public function __construct(private FinancePostingService $posting) {}

    /** GET /api/v1/finance-journals — jurnallar (entry sayı ilə). */
    public function index(): JsonResponse
    {
        $journals = FinanceJournal::withCount('entries')->orderByDesc('journal_date')->orderByDesc('created_at')->get();

        return response()->json(['data' => $journals->map(fn (FinanceJournal $j) => $this->header($j))->all()]);
    }

    /** POST /api/v1/finance-journals — yeni jurnal (tarixlə). */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'journal_date' => ['required', 'date'],
            'descr' => ['nullable', 'string', 'max:255'],
        ]);

        $journal = FinanceJournal::create([
            'code' => 'FJ'.strtoupper(substr((string) Str::ulid(), -8)),
            'journal_date' => $data['journal_date'],
            'descr' => $data['descr'] ?? null,
            'resp_person' => $request->user()?->username,
        ]);

        return response()->json($this->show($journal), 201);
    }

    /** GET /api/v1/finance-journals/{financeJournal} — başlıq + sətirlər. */
    public function showJournal(FinanceJournal $financeJournal): JsonResponse
    {
        return response()->json($this->show($financeJournal));
    }

    /** POST /api/v1/finance-journals/{financeJournal}/entries — draft sətir əlavə. */
    public function addEntry(Request $request, FinanceJournal $financeJournal): JsonResponse
    {
        $data = $this->validateEntry($request);

        FinanceJournalEntry::create([
            'jnl_code' => $financeJournal->code,
            'posting_date' => $data['posting_date'] ?? $financeJournal->journal_date->toDateString(),
            'entry_type' => $data['entry_type'],
            'cash_desk_code' => $data['cash_desk_code'],
            'to_cash_desk_code' => $data['to_cash_desk_code'] ?? null,
            'category_code' => $data['category_code'] ?? null,
            'amount_lcy' => $data['amount_lcy'],
            'descr' => $data['descr'] ?? null,
            'resp_person' => $request->user()?->username,
        ]);

        return response()->json($this->show($financeJournal), 201);
    }

    /** PATCH /api/v1/finance-journals/{financeJournal}/entries/{entry} */
    public function updateEntry(Request $request, FinanceJournal $financeJournal, FinanceJournalEntry $entry): JsonResponse
    {
        abort_unless($entry->jnl_code === $financeJournal->code, 404);
        $data = $this->validateEntry($request);

        $entry->update([
            'posting_date' => $data['posting_date'] ?? $entry->posting_date?->toDateString() ?? $financeJournal->journal_date->toDateString(),
            'entry_type' => $data['entry_type'],
            'cash_desk_code' => $data['cash_desk_code'],
            'to_cash_desk_code' => $data['to_cash_desk_code'] ?? null,
            'category_code' => $data['category_code'] ?? null,
            'amount_lcy' => $data['amount_lcy'],
            'descr' => $data['descr'] ?? null,
        ]);

        return response()->json($this->show($financeJournal));
    }

    /** DELETE /api/v1/finance-journals/{financeJournal}/entries/{entry} */
    public function deleteEntry(FinanceJournal $financeJournal, FinanceJournalEntry $entry): JsonResponse
    {
        abort_unless($entry->jnl_code === $financeJournal->code, 404);
        $entry->delete();

        return response()->json($this->show($financeJournal));
    }

    /** PUT /api/v1/finance-journals/{financeJournal}/entries/{entry}/lines — çek məhsul sətirlərini əvəz et */
    public function saveLines(Request $request, FinanceJournal $financeJournal, FinanceJournalEntry $entry): JsonResponse
    {
        abort_unless($entry->jnl_code === $financeJournal->code, 404);

        $data = $request->validate([
            'lines' => ['present', 'array'],
            'lines.*.item_code' => ['required', 'string', Rule::exists('items', 'code')],
            'lines.*.qty' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit_price' => ['required', 'numeric', 'gte:0'],
        ]);

        DB::transaction(function () use ($data, $entry) {
            $entry->lines()->delete();
            $total = 0.0;
            foreach (array_values($data['lines']) as $i => $line) {
                $item = Item::find($line['item_code']);
                $amount = round((float) $line['qty'] * (float) $line['unit_price'], 2);
                $total += $amount;
                FinanceJournalLine::create([
                    'entry_uid' => $entry->uid,
                    'item_code' => $line['item_code'],
                    'item_name' => $item?->name,
                    'measure_code' => $item?->base_measure_code,
                    'qty' => $line['qty'],
                    'unit_price' => $line['unit_price'],
                    'amount_lcy' => $amount,
                    'sort_order' => $i,
                ]);
            }
            // Sətirlər varsa entry məbləği = cəm
            if (! empty($data['lines'])) {
                $entry->update(['amount_lcy' => round($total, 2)]);
            }
        });

        return response()->json($this->show($financeJournal));
    }

    /** POST /api/v1/finance-journals/{financeJournal}/post */
    public function post(Request $request, FinanceJournal $financeJournal): JsonResponse
    {
        try {
            $posted = $this->posting->post($financeJournal, $request->user()?->username);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['ok' => true, 'message' => __('messages.posted'), 'posted' => $posted]);
    }

    /** DELETE /api/v1/finance-journals/{financeJournal} — jurnalı sil (draft sətirlər cascade). */
    public function destroy(FinanceJournal $financeJournal): JsonResponse
    {
        $financeJournal->delete();

        return response()->json(['message' => __('messages.deleted')]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateEntry(Request $request): array
    {
        $data = $request->validate([
            'posting_date' => ['nullable', 'date'],
            'entry_type' => ['required', Rule::in(['income', 'expense', 'transfer'])],
            'cash_desk_code' => ['required', 'string', Rule::exists('cash_desk', 'code')],
            'to_cash_desk_code' => ['nullable', 'string', Rule::exists('cash_desk', 'code')],
            'category_code' => ['nullable', 'string', Rule::exists('finance_categories', 'code')],
            'amount_lcy' => ['required', 'numeric', 'gt:0'],
            'descr' => ['nullable', 'string', 'max:255'],
        ]);

        if ($data['entry_type'] === 'transfer') {
            // Transfer: hədəf hesab lazımdır, mənbədən fərqli; kateqoriya olmur
            if (empty($data['to_cash_desk_code'])) {
                throw ValidationException::withMessages(['to_cash_desk_code' => __('messages.transfer_needs_target')]);
            }
            if ($data['to_cash_desk_code'] === $data['cash_desk_code']) {
                throw ValidationException::withMessages(['to_cash_desk_code' => __('messages.transfer_same_desk')]);
            }
            $data['category_code'] = null;
        } else {
            $data['to_cash_desk_code'] = null;
            // Kateqoriya növü entry növü ilə uyğun olmalı (gəlirdə income kateqoriyası)
            if (! empty($data['category_code'])) {
                $cat = FinanceCategory::find($data['category_code']);
                if ($cat && $cat->type->value !== $data['entry_type']) {
                    throw ValidationException::withMessages(['category_code' => __('messages.finance_type_mismatch')]);
                }
            }
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function header(FinanceJournal $j): array
    {
        return [
            'code' => $j->code,
            'journal_date' => $j->journal_date?->toDateString(),
            'descr' => $j->descr,
            'resp_person' => $j->resp_person,
            'entries_count' => $j->entries_count ?? $j->entries()->count(),
            'created_at' => $j->created_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function show(FinanceJournal $j): array
    {
        $entries = $j->entries()->with('lines')->orderBy('created_at')->get()->map(fn (FinanceJournalEntry $e) => [
            'uid' => $e->uid,
            'posting_date' => $e->posting_date?->toDateString(),
            'entry_type' => $e->entry_type->value,
            'cash_desk_code' => $e->cash_desk_code,
            'to_cash_desk_code' => $e->to_cash_desk_code,
            'category_code' => $e->category_code,
            'amount_lcy' => $e->amount_lcy,
            'descr' => $e->descr,
            'lines' => $e->lines->sortBy('sort_order')->values()->map(fn (FinanceJournalLine $l) => [
                'uid' => $l->uid,
                'item_code' => $l->item_code,
                'item_name' => $l->item_name,
                'measure_code' => $l->measure_code,
                'qty' => $l->qty,
                'unit_price' => $l->unit_price,
                'amount_lcy' => $l->amount_lcy,
            ])->all(),
        ])->all();

        return ['journal' => $this->header($j), 'entries' => $entries];
    }
}
