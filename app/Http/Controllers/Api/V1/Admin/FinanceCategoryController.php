<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\FinanceCategoryType;
use App\Http\Controllers\Controller;
use App\Models\FinanceCategory;
use App\Support\Translatable;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class FinanceCategoryController extends Controller
{
    /** GET /api/v1/finance-categories — flat (frontend ağac qurur, type üzrə ayrılır) */
    public function index(): JsonResponse
    {
        return response()->json(
            FinanceCategory::orderBy('sort_order')->orderBy('code')->get()->map(fn (FinanceCategory $c) => $this->payload($c))
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:30', 'regex:/^[A-Z][A-Z0-9_]*$/', 'unique:finance_categories,code'],
            'parent_code' => ['nullable', 'string', Rule::exists('finance_categories', 'code')],
            'type' => ['required', Rule::enum(FinanceCategoryType::class)],
            ...Translatable::rules('name'),
        ]);

        // Alt-kateqoriya parent-in type-ını miras alır (income altına expense düşə bilməz)
        $type = $data['type'];
        if (! empty($data['parent_code'])) {
            $type = FinanceCategory::find($data['parent_code'])?->type->value ?? $type;
        }

        $nextOrder = (int) FinanceCategory::where('parent_code', $data['parent_code'] ?? null)->max('sort_order') + 1;

        $category = FinanceCategory::create([
            'code' => $data['code'],
            'parent_code' => $data['parent_code'] ?? null,
            'name' => Translatable::sanitize($request->input('name', [])),
            'type' => $type,
            'sort_order' => $nextOrder,
        ]);

        return response()->json($this->payload($category), 201);
    }

    public function update(Request $request, FinanceCategory $financeCategory): JsonResponse
    {
        $data = $request->validate([
            'parent_code' => ['sometimes', 'nullable', 'string', Rule::exists('finance_categories', 'code')],
            ...Translatable::rules('name'),
        ]);

        // parent dəyişəndə: dövr yoxlaması + type uyğunluğu (eyni ağac içində qalmalı)
        if (array_key_exists('parent_code', $data) && $data['parent_code'] !== null) {
            if ($this->wouldCycle($financeCategory->code, $data['parent_code'])) {
                return response()->json(['message' => __('messages.category_cycle')], 422);
            }
            $parentType = FinanceCategory::find($data['parent_code'])?->type;
            if ($parentType && $parentType !== $financeCategory->type) {
                return response()->json(['message' => __('messages.finance_type_mismatch')], 422);
            }
        }

        $payload = ['name' => Translatable::sanitize($request->input('name', []))];
        if (array_key_exists('parent_code', $data)) {
            $payload['parent_code'] = $data['parent_code'];
        }

        $financeCategory->update($payload);

        return response()->json($this->payload($financeCategory));
    }

    public function destroy(FinanceCategory $financeCategory): JsonResponse
    {
        if (FinanceCategory::where('parent_code', $financeCategory->code)->exists()) {
            return response()->json(['message' => __('messages.category_has_children')], 422);
        }
        if ($financeCategory->in_use) {
            return response()->json(['message' => __('messages.in_use_locked')], 422);
        }

        try {
            $financeCategory->delete();
        } catch (QueryException $e) {
            if ($e->getCode() === '23503') {
                $financeCategory->update(['in_use' => true]);

                return response()->json(['message' => __('messages.in_use_locked')], 422);
            }
            throw $e;
        }

        return response()->json(['message' => __('messages.deleted')]);
    }

    /** PUT /api/v1/finance-categories/reorder — drag-drop (parent + sort_order, type dəyişmir). */
    public function reorder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array'],
            'items.*.code' => ['required', 'string', Rule::exists('finance_categories', 'code')],
            'items.*.parent_code' => ['nullable', 'string'],
            'items.*.sort_order' => ['required', 'integer'],
        ]);

        $typeOf = FinanceCategory::pluck('type', 'code');
        foreach ($data['items'] as $it) {
            if ($this->wouldCycle($it['code'], $it['parent_code'] ?? null)) {
                return response()->json(['message' => __('messages.category_cycle')], 422);
            }
            // Fərqli type-lı ağaca köçürməyə icazə yox
            if (! empty($it['parent_code']) && ($typeOf[$it['parent_code']] ?? null) !== ($typeOf[$it['code']] ?? null)) {
                return response()->json(['message' => __('messages.finance_type_mismatch')], 422);
            }
        }

        DB::transaction(function () use ($data) {
            foreach ($data['items'] as $it) {
                FinanceCategory::where('code', $it['code'])->update([
                    'parent_code' => $it['parent_code'] ?? null,
                    'sort_order' => $it['sort_order'],
                ]);
            }
        });

        return response()->json(['message' => __('messages.saved')]);
    }

    private function wouldCycle(string $code, ?string $newParent): bool
    {
        if ($newParent === null) {
            return false;
        }
        if ($newParent === $code) {
            return true;
        }
        $parentOf = FinanceCategory::pluck('parent_code', 'code');
        $cursor = $newParent;
        $guard = 0;
        while ($cursor !== null && $guard++ < 1000) {
            if ($cursor === $code) {
                return true;
            }
            $cursor = $parentOf[$cursor] ?? null;
        }

        return false;
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(FinanceCategory $c): array
    {
        return [
            'code' => $c->code,
            'parent_code' => $c->parent_code,
            'name' => $c->name,
            'type' => $c->type->value,
            'sort_order' => $c->sort_order,
            'in_use' => (bool) $c->in_use,
        ];
    }
}
