<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\CategoryStatus;
use App\Http\Controllers\Controller;
use App\Models\ItemCategory;
use App\Support\Translatable;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    /** GET /api/v1/categories — flat (frontend ağac qurur) */
    public function index(): JsonResponse
    {
        return response()->json(
            ItemCategory::orderBy('sort_order')->orderBy('code')->get()->map(fn (ItemCategory $c) => $this->payload($c))
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:30', 'regex:/^[A-Z][A-Z0-9_]*$/', 'unique:item_categories,code'],
            'parent_code' => ['nullable', 'string', Rule::exists('item_categories', 'code')],
            'status' => ['sometimes', Rule::enum(CategoryStatus::class)],
            ...Translatable::rules('name'),
        ]);

        $nextOrder = (int) ItemCategory::where('parent_code', $data['parent_code'] ?? null)->max('sort_order') + 1;

        $category = ItemCategory::create([
            'code' => $data['code'],
            'parent_code' => $data['parent_code'] ?? null,
            'name' => Translatable::sanitize($request->input('name', [])),
            'status' => $data['status'] ?? CategoryStatus::Active->value,
            'sort_order' => $nextOrder,
        ]);

        return response()->json($this->payload($category), 201);
    }

    public function update(Request $request, ItemCategory $category): JsonResponse
    {
        $data = $request->validate([
            'parent_code' => ['sometimes', 'nullable', 'string', Rule::exists('item_categories', 'code')],
            'status' => ['sometimes', Rule::enum(CategoryStatus::class)],
            ...Translatable::rules('name'),
        ]);

        if (array_key_exists('parent_code', $data) && $this->wouldCycle($category->code, $data['parent_code'])) {
            return response()->json(['message' => __('messages.category_cycle')], 422);
        }

        $payload = ['name' => Translatable::sanitize($request->input('name', []))];
        if (array_key_exists('parent_code', $data)) {
            $payload['parent_code'] = $data['parent_code'];
        }
        if (isset($data['status'])) {
            $payload['status'] = $data['status'];
        }

        $category->update($payload);

        return response()->json($this->payload($category));
    }

    public function destroy(ItemCategory $category): JsonResponse
    {
        if (ItemCategory::where('parent_code', $category->code)->exists()) {
            return response()->json(['message' => __('messages.category_has_children')], 422);
        }
        if ($category->in_use) {
            return response()->json(['message' => __('messages.in_use_locked')], 422);
        }

        try {
            $category->delete();
        } catch (QueryException $e) {
            if ($e->getCode() === '23503') {
                $category->update(['in_use' => true]);

                return response()->json(['message' => __('messages.in_use_locked')], 422);
            }
            throw $e;
        }

        return response()->json(['message' => __('messages.deleted')]);
    }

    /** PUT /api/v1/categories/reorder — drag-drop (parent + sort_order). */
    public function reorder(Request $request): JsonResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array'],
            'items.*.code' => ['required', 'string', Rule::exists('item_categories', 'code')],
            'items.*.parent_code' => ['nullable', 'string'],
            'items.*.sort_order' => ['required', 'integer'],
        ]);

        foreach ($data['items'] as $it) {
            if ($this->wouldCycle($it['code'], $it['parent_code'] ?? null)) {
                return response()->json(['message' => __('messages.category_cycle')], 422);
            }
        }

        DB::transaction(function () use ($data) {
            foreach ($data['items'] as $it) {
                ItemCategory::where('code', $it['code'])->update([
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
        $parentOf = ItemCategory::pluck('parent_code', 'code');
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
    private function payload(ItemCategory $c): array
    {
        return [
            'code' => $c->code,
            'parent_code' => $c->parent_code,
            'name' => $c->name,
            'status' => $c->status->value,
            'sort_order' => $c->sort_order,
            'in_use' => (bool) $c->in_use,
        ];
    }
}
