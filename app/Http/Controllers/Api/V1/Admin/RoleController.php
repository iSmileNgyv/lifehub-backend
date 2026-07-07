<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    /** GET /api/v1/roles */
    public function index(): JsonResponse
    {
        return response()->json(
            Role::orderBy('name')->get(['code', 'name'])
        );
    }

    /** POST /api/v1/roles */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', 'regex:/^[A-Z][A-Z0-9_]*$/', 'unique:App\Models\Role,code'],
            'name' => ['required', 'string', 'max:100'],
        ]);

        $role = Role::create($data);

        return response()->json($role->only('code', 'name'), 201);
    }

    /** PATCH /api/v1/roles/{role} — yalnız ad dəyişir (code sabitdir) */
    public function update(Request $request, Role $role): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
        ]);

        $role->update($data);

        return response()->json($role->only('code', 'name'));
    }

    /** DELETE /api/v1/roles/{role} */
    public function destroy(Role $role): JsonResponse
    {
        $role->delete();

        return response()->json(['message' => __('messages.role_deleted')]);
    }
}
