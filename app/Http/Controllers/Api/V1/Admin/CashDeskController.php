<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\CashDeskStatus;
use App\Http\Controllers\Controller;
use App\Models\CashDesk;
use App\Support\Translatable;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CashDeskController extends Controller
{
    /** GET /api/v1/cash-desks?q=&page= */
    public function index(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        $query = CashDesk::query()->orderBy('code');

        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('code', 'ilike', "%{$q}%")
                    ->orWhereRaw('description::text ILIKE ?', ["%{$q}%"]);
            });
        }

        $desks = $query->paginate(20);

        return response()->json([
            'data' => collect($desks->items())->map(fn (CashDesk $d) => $this->payload($d))->all(),
            'current_page' => $desks->currentPage(),
            'last_page' => $desks->lastPage(),
            'total' => $desks->total(),
        ]);
    }

    /** POST /api/v1/cash-desks — code manual */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:30', 'regex:/^[A-Z][A-Z0-9_]*$/', 'unique:App\Models\CashDesk,code'],
            ...$this->commonRules(),
        ]);

        $desk = CashDesk::create([
            'code' => $data['code'],
            'balance_lcy' => 0,
            ...$this->writable($request, $data),
        ]);

        return response()->json($this->payload($desk), 201);
    }

    /** PATCH /api/v1/cash-desks/{cashDesk} — code dəyişmir, balance_lcy formadan yenilənmir */
    public function update(Request $request, CashDesk $cashDesk): JsonResponse
    {
        $data = $request->validate($this->commonRules());

        $cashDesk->update($this->writable($request, $data));

        return response()->json($this->payload($cashDesk));
    }

    /** DELETE /api/v1/cash-desks/{cashDesk} */
    public function destroy(CashDesk $cashDesk): JsonResponse
    {
        if ($cashDesk->in_use) {
            return response()->json(['message' => __('messages.in_use_locked')], 422);
        }

        try {
            $cashDesk->delete();
        } catch (QueryException $e) {
            if ($e->getCode() === '23503') {
                $cashDesk->update(['in_use' => true]);

                return response()->json(['message' => __('messages.in_use_locked')], 422);
            }
            throw $e;
        }

        return response()->json(['message' => __('messages.deleted')]);
    }

    /**
     * @return array<string, mixed>
     */
    private function commonRules(): array
    {
        return [
            'status' => ['sometimes', Rule::enum(CashDeskStatus::class)],
            'address' => ['nullable', 'string', 'max:500'],
            'resp_person' => ['nullable', 'string', 'max:255'],
            ...Translatable::rules('description'),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function writable(Request $request, array $data): array
    {
        return [
            'description' => Translatable::sanitize($request->input('description', [])),
            'address' => $data['address'] ?? null,
            'resp_person' => $data['resp_person'] ?? null,
            'status' => $data['status'] ?? CashDeskStatus::Active->value,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(CashDesk $d): array
    {
        return [
            'code' => $d->code,
            'description' => $d->description,
            'address' => $d->address,
            'resp_person' => $d->resp_person,
            'balance_lcy' => $d->balance_lcy,
            'status' => $d->status->value,
            'in_use' => (bool) $d->in_use,
        ];
    }
}
