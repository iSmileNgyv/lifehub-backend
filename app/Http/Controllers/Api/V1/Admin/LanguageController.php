<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Language;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class LanguageController extends Controller
{
    /** GET /api/v1/languages — aktiv dillər (formalar üçün, hər kəsə) */
    public function index(): JsonResponse
    {
        return response()->json(
            Language::where('is_active', true)->orderBy('sort_order')->orderBy('code')
                ->get(['code', 'name', 'is_default'])
        );
    }

    /** GET /api/v1/languages/all — bütün dillər (idarəetmə) */
    public function all(): JsonResponse
    {
        return response()->json(
            Language::orderBy('sort_order')->orderBy('code')->get()
        );
    }

    /** POST /api/v1/languages — yeni dil (məs. 'de') */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:10', 'regex:/^[a-z]{2,10}$/', 'unique:App\Models\Language,code'],
            'name' => ['required', 'string', 'max:100'],
            'is_active' => ['boolean'],
            'is_default' => ['boolean'],
            'sort_order' => ['integer'],
        ]);

        $language = DB::transaction(function () use ($data) {
            if ($data['is_default'] ?? false) {
                Language::where('is_default', true)->update(['is_default' => false]);
            }

            return Language::create($data);
        });

        return response()->json($language, 201);
    }

    /** PATCH /api/v1/languages/{language} */
    public function update(Request $request, Language $language): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:100'],
            'is_active' => ['sometimes', 'boolean'],
            'is_default' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer'],
        ]);

        DB::transaction(function () use ($data, $language) {
            if (($data['is_default'] ?? false) === true) {
                Language::where('is_default', true)->where('code', '!=', $language->code)
                    ->update(['is_default' => false]);
            }
            $language->update($data);
        });

        return response()->json($language->fresh());
    }
}
