<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\UserStatus;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    /** GET /api/v1/users?q=&page= — paginasiya + axtarış (infinite scroll üçün) */
    public function index(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        $query = User::query()->with('roles')->orderBy('name')->orderBy('username');

        if ($q !== '') {
            $query->where(function ($w) use ($q) {
                $w->where('name', 'ilike', "%{$q}%")
                    ->orWhere('username', 'ilike', "%{$q}%");
            });
        }

        $users = $query->paginate(20);
        $withTg = (bool) $request->user()?->hasOperation('USER_TELEGRAM');

        return response()->json([
            'data' => collect($users->items())->map(fn (User $u) => $this->payload($u, $withTg))->all(),
            'current_page' => $users->currentPage(),
            'last_page' => $users->lastPage(),
            'total' => $users->total(),
        ]);
    }

    /** POST /api/v1/users/{user}/telegram-code — admin istifadəçi üçün bağlama kodu yaradır (access:USER_TELEGRAM). */
    public function telegramCode(User $user): JsonResponse
    {
        $code = strtoupper(\Illuminate\Support\Str::random(8));
        $user->forceFill([
            'telegram_link_code' => $code,
            'telegram_link_expires_at' => now()->addMinutes(30),
        ])->save();

        return response()->json(['code' => $code, 'bot_username' => config('services.telegram.username'), 'expires_min' => 30]);
    }

    /** POST /api/v1/users/{user}/telegram-unlink — istifadəçinin bot bağlantısını ayır (access:USER_TELEGRAM). */
    public function telegramUnlink(User $user): JsonResponse
    {
        $user->forceFill([
            'telegram_chat_id' => null,
            'telegram_link_code' => null,
            'telegram_link_expires_at' => null,
        ])->save();

        return response()->json(['ok' => true]);
    }

    /** POST /api/v1/users — yeni istifadəçi (qeydiyyat) */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => ['required', 'string', 'max:255', Rule::unique('users', 'username')],
            'password' => ['required', 'string', Password::min(8)],
            'status' => ['required', Rule::enum(UserStatus::class)],
        ]);

        $user = User::create($data);

        return response()->json($this->payload($user), 201);
    }

    /** PATCH /api/v1/users/{user} — ad / username / status (qismən) */
    public function update(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'username' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('users', 'username')->ignore($user->uid, 'uid')],
            'status' => ['sometimes', 'required', Rule::enum(UserStatus::class)],
        ]);

        $user->update($data);

        return response()->json($this->payload($user));
    }

    /** PUT /api/v1/users/{user}/password — admin başqasının şifrəsini təyin edir */
    public function setPassword(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'new_password' => ['required', 'string', Password::min(8), 'confirmed'],
        ]);

        $user->update(['password' => $data['new_password']]);

        return response()->json(['message' => __('messages.password_updated')]);
    }

    /** PUT /api/v1/users/{user}/roles — istifadəçinin rollarını sync edir (çox rol) */
    public function syncRoles(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'roles' => ['present', 'array'],
            'roles.*' => ['string', Rule::exists('roles', 'code')],
        ]);

        $user->roles()->sync($data['roles']);

        return response()->json($this->payload($user->load('roles')));
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(User $user, bool $withTelegram = false): array
    {
        $data = [
            'uid' => $user->uid,
            'name' => $user->name,
            'username' => $user->username,
            'status' => $user->status->value,
            'is_super_admin' => (bool) $user->is_super_admin,
            'created_at' => $user->created_at?->toIso8601String(),
            'roles' => $user->roles->map(fn ($r) => ['code' => $r->code, 'name' => $r->name])->values()->all(),
        ];
        // Telegram bağlantı statusu yalnız icazə olanda (USER_TELEGRAM)
        if ($withTelegram) {
            $data['telegram_linked'] = ! empty($user->telegram_chat_id);
        }

        return $data;
    }
}
