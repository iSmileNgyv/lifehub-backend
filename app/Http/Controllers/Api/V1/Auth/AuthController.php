<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\Language;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    /**
     * POST /api/v1/auth/login
     */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('username', $data['username'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return response()->json([
                'message' => __('messages.invalid_credentials'),
            ], 401);
        }

        if (! $user->status->canLogin()) {
            return response()->json([
                'message' => __('messages.account_inactive'),
                'status' => $user->status->value,
            ], 403);
        }

        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $this->userPayload($user),
            'permissions' => $user->permissionCodes(),
            'roles' => $this->rolesPayload($user),
        ]);
    }

    /**
     * POST /api/v1/auth/logout — cari token-i silir.
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => __('messages.logged_out')]);
    }

    /**
     * GET /api/v1/auth/me — cari istifadəçi.
     */
    public function me(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'user' => $this->userPayload($user),
            'permissions' => $user->permissionCodes(),
            'roles' => $this->rolesPayload($user),
        ]);
    }

    /**
     * PATCH /api/v1/auth/profile — istifadəçi öz adını/username-ini dəyişir.
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => [
                'required', 'string', 'max:255',
                // 'users' (qualified deyil) — search_path admin-i əhatə edir;
                // 'admin.users' yazsaq Rule nöqtəni connection.table kimi parçalayır.
                Rule::unique('users', 'username')->ignore($user->uid, 'uid'),
            ],
        ]);

        $user->update($data);

        return response()->json([
            'user' => $this->userPayload($user),
            'roles' => $this->rolesPayload($user),
        ]);
    }

    /**
     * PUT /api/v1/auth/password — cari şifrəni yoxlayıb yenisini qoyur.
     */
    public function changePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $request->user();

        if (! Hash::check($data['current_password'], $user->password)) {
            return response()->json(['message' => __('messages.current_password_wrong')], 422);
        }

        $user->update(['password' => $data['new_password']]);

        return response()->json(['message' => __('messages.password_changed')]);
    }

    /**
     * PUT /api/v1/auth/language — istifadəçinin dilini yadda saxlayır.
     */
    public function updateLanguage(Request $request): JsonResponse
    {
        $data = $request->validate([
            'language' => ['required', Rule::enum(Language::class)],
        ]);

        $request->user()->update(['language' => $data['language']]);

        return response()->json(['language' => $data['language']]);
    }

    /**
     * @return array<string, mixed>
     */
    private function userPayload(User $user): array
    {
        return [
            'uid' => $user->uid,
            'name' => $user->name,
            'username' => $user->username,
            'status' => $user->status->value,
            'is_super_admin' => $user->is_super_admin,
            // Migration hələ tətbiq olunmaya bilər (sütun yoxdursa null) — default 'az'
            'language' => $user->language?->value ?? 'az',
        ];
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function rolesPayload(User $user): array
    {
        return $user->roles()->get(['admin.roles.code', 'admin.roles.name'])
            ->map(fn ($r) => ['code' => $r->code, 'name' => $r->name])
            ->all();
    }
}
