<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Operation;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Rolun icazə matrisi. UI: operation-u tutub rola atmaq = access=1;
 * qıfıl = access=0; zibil = sətri silmək.
 */
class RoleAccessController extends Controller
{
    /** GET /api/v1/roles/{role}/access — rolun mövcud sətirləri */
    public function index(Role $role): JsonResponse
    {
        $rows = DB::table('admin.role_access')
            ->where('role_code', $role->code)
            ->get(['operation_code', 'access']);

        return response()->json($rows);
    }

    /**
     * PUT /api/v1/roles/{role}/access/{operation}
     * Sətir yaradır/yeniləyir. body: { access: bool }. (drag-in → 1, qıfıl → 0)
     */
    public function upsert(Request $request, Role $role, Operation $operation): JsonResponse
    {
        $data = $request->validate([
            'access' => ['required', 'boolean'],
        ]);

        DB::table('admin.role_access')->updateOrInsert(
            ['role_code' => $role->code, 'operation_code' => $operation->code],
            ['access' => $data['access'], 'updated_at' => now(), 'created_at' => now()],
        );

        return response()->json([
            'operation_code' => $operation->code,
            'access' => $data['access'],
        ]);
    }

    /** DELETE /api/v1/roles/{role}/access/{operation} — sətri tamam silir (zibil) */
    public function destroy(Role $role, Operation $operation): JsonResponse
    {
        DB::table('admin.role_access')
            ->where('role_code', $role->code)
            ->where('operation_code', $operation->code)
            ->delete();

        return response()->json(['message' => __('messages.deleted')]);
    }
}

