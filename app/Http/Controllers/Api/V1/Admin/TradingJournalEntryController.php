<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\TradingFormula;
use App\Models\TradingJournal;
use App\Models\TradingJournalEntry;
use App\Support\FormulaEvaluator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class TradingJournalEntryController extends Controller
{
    /** GET /api/v1/trading/journals/{journal}/entries */
    public function index(TradingJournal $journal): JsonResponse
    {
        $entries = $journal->entries()->orderBy('created_at')->get();

        return response()->json(['data' => $entries->map(fn (TradingJournalEntry $e) => $this->payload($e))->all()]);
    }

    /** POST /api/v1/trading/journals/{journal}/entries — buy/sell sətri */
    public function store(Request $request, TradingJournal $journal): JsonResponse
    {
        if ($journal->status !== 'draft') {
            return response()->json(['message' => 'Post olunmuş jurnala sətir əlavə olunmaz.'], 422);
        }

        $data = $request->validate([
            'entry_type' => ['required', 'in:buy,sell'],
            'manat_amount' => ['required', 'numeric', 'gt:0'],
            'usd_qty' => ['nullable', 'numeric', 'gt:0'],
            'descr' => ['nullable', 'string', 'max:200'],
        ]);

        try {
            $usd = $this->resolveUsd($data);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $entry = $journal->entries()->create([
            'entry_type' => $data['entry_type'],
            'manat_amount' => round((float) $data['manat_amount'], 2),
            'usd_qty' => round($usd, 4),
            'descr' => $data['descr'] ?? null,
        ]);

        return response()->json($this->payload($entry), 201);
    }

    /** PATCH /api/v1/trading/journals/{journal}/entries/{entry} */
    public function update(Request $request, TradingJournal $journal, TradingJournalEntry $entry): JsonResponse
    {
        if ($journal->status !== 'draft') {
            return response()->json(['message' => 'Post olunmuş jurnal sətri dəyişdirilə bilməz.'], 422);
        }

        $data = $request->validate([
            'entry_type' => ['required', 'in:buy,sell'],
            'manat_amount' => ['required', 'numeric', 'gt:0'],
            'usd_qty' => ['nullable', 'numeric', 'gt:0'],
            'descr' => ['nullable', 'string', 'max:200'],
        ]);

        try {
            $usd = $this->resolveUsd($data);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $entry->update([
            'entry_type' => $data['entry_type'],
            'manat_amount' => round((float) $data['manat_amount'], 2),
            'usd_qty' => round($usd, 4),
            'descr' => $data['descr'] ?? null,
        ]);

        return response()->json($this->payload($entry));
    }

    /** DELETE /api/v1/trading/journals/{journal}/entries/{entry} */
    public function destroy(TradingJournal $journal, TradingJournalEntry $entry): JsonResponse
    {
        if ($journal->status !== 'draft') {
            return response()->json(['message' => 'Post olunmuş jurnal sətri silinə bilməz.'], 422);
        }

        $entry->delete();

        return response()->json(['message' => __('messages.deleted')]);
    }

    /**
     * USD miqdarını həll et: buy → daxil edilməlidir; sell → override varsa götür, yoxsa aktiv formuladan.
     *
     * @param  array<string, mixed>  $data
     */
    private function resolveUsd(array $data): float
    {
        if (isset($data['usd_qty']) && $data['usd_qty'] !== null) {
            return (float) $data['usd_qty'];
        }

        if ($data['entry_type'] === 'buy') {
            throw new RuntimeException('Alış üçün USD miqdarı daxil edilməlidir.');
        }

        // sell → aktiv formula
        $active = TradingFormula::where('is_active', true)->first();
        if (! $active) {
            throw new RuntimeException('Aktiv formula yoxdur — USD hesablana bilmir.');
        }

        return FormulaEvaluator::apply($active->tiers, (float) $data['manat_amount'])['result'];
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(TradingJournalEntry $e): array
    {
        return [
            'uid' => $e->uid,
            'journal_code' => $e->journal_code,
            'entry_type' => $e->entry_type->value,
            'manat_amount' => $e->manat_amount,
            'usd_qty' => $e->usd_qty,
            'descr' => $e->descr,
        ];
    }
}
