<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    /** GET /api/v1/settings — sistem ayarları. */
    public function index(): JsonResponse
    {
        return response()->json([
            'registration_enabled' => Setting::getBool('registration_enabled', false),
        ]);
    }

    /** PUT /api/v1/settings — ayarları yenilə (SETTINGS_MANAGE). */
    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'registration_enabled' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('registration_enabled', $data)) {
            Setting::put('registration_enabled', $data['registration_enabled'] ? '1' : '0');
        }

        return $this->index();
    }
}
