<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\ItemStatus;
use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\StoredFile;
use App\Storage\StorageFactory;
use App\Support\Translatable;
use App\Support\Usage;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ItemController extends Controller
{
    /**
     * Kateqoriya + bütün alt-kateqoriyalarının kodları.
     *
     * @return array<int, string>
     */
    private function categoryTree(string $root): array
    {
        $childrenBy = [];
        foreach (ItemCategory::select('code', 'parent_code')->get() as $c) {
            $childrenBy[$c->parent_code][] = $c->code;
        }
        $result = [$root];
        $stack = [$root];
        while ($stack) {
            $cur = array_pop($stack);
            foreach ($childrenBy[$cur] ?? [] as $child) {
                $result[] = $child;
                $stack[] = $child;
            }
        }

        return $result;
    }

    /** GET /api/v1/items?q=&page=&category_code= */
    public function index(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $query = Item::query()->orderBy('code');

        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('code', 'ilike', "%{$q}%")->orWhereRaw('name::text ILIKE ?', ["%{$q}%"]);
            });
        }
        if ($cat = trim((string) $request->query('category_code', ''))) {
            $query->whereIn('category_code', $this->categoryTree($cat))->where('status', 'ACTIVE');
        }

        $items = $query->paginate(20);

        return response()->json([
            'data' => collect($items->items())->map(fn (Item $i) => $this->payload($i))->all(),
            'current_page' => $items->currentPage(),
            'last_page' => $items->lastPage(),
            'total' => $items->total(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validateData($request, isUpdate: false);

        $item = Item::create([
            'code' => $data['code'],
            'name' => Translatable::sanitize($request->input('name', [])),
            'category_code' => $data['category_code'] ?? null,
            'base_measure_code' => $data['base_measure_code'],
            'status' => $data['status'] ?? ItemStatus::Active->value,
            'image' => $data['image'] ?? null,
        ]);

        $this->recomputeUsage($item->category_code, $item->base_measure_code);
        $this->syncBarcodes($item, $request);

        return response()->json($this->payload($item), 201);
    }

    public function update(Request $request, Item $item): JsonResponse
    {
        $data = $this->validateData($request, isUpdate: true);

        $oldCategory = $item->category_code;
        $oldMeasure = $item->base_measure_code;
        $oldImage = $item->image;
        $newImage = $data['image'] ?? null;

        $item->update([
            'name' => Translatable::sanitize($request->input('name', [])),
            'category_code' => $data['category_code'] ?? null,
            'base_measure_code' => $data['base_measure_code'],
            'status' => $data['status'] ?? $item->status->value,
            'image' => $newImage,
        ]);

        if ($oldImage && $oldImage !== $newImage) {
            $this->deleteStoredFile($oldImage);
        }

        $this->recomputeUsage($oldCategory, $oldMeasure);
        $this->recomputeUsage($item->category_code, $item->base_measure_code);
        $this->syncBarcodes($item, $request);

        return response()->json($this->payload($item));
    }

    public function destroy(Item $item): JsonResponse
    {
        if ($item->in_use) {
            return response()->json(['message' => __('messages.in_use_locked')], 422);
        }

        $category = $item->category_code;
        $measure = $item->base_measure_code;
        $image = $item->image;

        try {
            $item->delete();
        } catch (QueryException $e) {
            if ($e->getCode() === '23503') {
                $item->update(['in_use' => true]);

                return response()->json(['message' => __('messages.in_use_locked')], 422);
            }
            throw $e;
        }

        $this->deleteStoredFile($image);
        $this->recomputeUsage($category, $measure);

        return response()->json(['message' => __('messages.deleted')]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validateData(Request $request, bool $isUpdate): array
    {
        return $request->validate([
            'code' => $isUpdate
                ? ['prohibited']
                : ['required', 'string', 'max:30', 'regex:/^[A-Z][A-Z0-9_]*$/', 'unique:items,code'],
            'category_code' => ['nullable', 'string', Rule::exists('item_categories', 'code')],
            'base_measure_code' => ['required', 'string', Rule::exists('measurements', 'code')],
            'status' => ['sometimes', Rule::enum(ItemStatus::class)],
            'image' => ['nullable', 'string', Rule::exists('stored_files', 'uid')],
            'barcodes' => ['sometimes', 'array'],
            'barcodes.*' => ['string', 'max:100'],
            ...Translatable::rules('name'),
        ]);
    }

    /** Barkodları sinxronla (bir barkod → bir məhsul). */
    private function syncBarcodes(Item $item, Request $request): void
    {
        if (! $request->has('barcodes')) {
            return;
        }
        $barcodes = collect($request->input('barcodes', []))->map(fn ($b) => trim((string) $b))->filter()->unique()->values();

        DB::table('app.item_barcodes')->where('item_code', $item->code)->whereNotIn('barcode', $barcodes)->delete();
        foreach ($barcodes as $bc) {
            DB::table('app.item_barcodes')->updateOrInsert(['barcode' => $bc], ['item_code' => $item->code]);
        }
    }

    private function deleteStoredFile(?string $uid): void
    {
        if (! $uid) {
            return;
        }
        $stored = StoredFile::find($uid);
        if (! $stored) {
            return;
        }
        try {
            StorageFactory::make($stored->driver)->delete($stored->path);
        } catch (\Throwable) {
            // storage xətası silməni dayandırmasın
        }
        $stored->delete();
    }

    private function recomputeUsage(?string $categoryCode, ?string $measureCode): void
    {
        Usage::category($categoryCode);
        Usage::measurement($measureCode);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(Item $i): array
    {
        return [
            'code' => $i->code,
            'name' => $i->name,
            'category_code' => $i->category_code,
            'base_measure_code' => $i->base_measure_code,
            'status' => $i->status->value,
            'image' => $i->image,
            'in_use' => (bool) $i->in_use,
            'barcodes' => DB::table('app.item_barcodes')->where('item_code', $i->code)->orderBy('barcode')->pluck('barcode')->all(),
        ];
    }
}
