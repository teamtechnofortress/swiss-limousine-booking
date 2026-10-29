<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\RbacException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

final class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $email = mb_strtolower((string) $request->input('email'));
        $password = (string) $request->input('password');

        $user = User::query()->where('email', $email)->first();

        // One response for every failure (unknown email, wrong password, inactive, no role),
        // so the endpoint never reveals which accounts exist.
        if (
            $user === null
            || ! Hash::check($password, $user->password)
            || ! $user->is_active
            || ! $user->roles()->exists()
        ) {
            throw RbacException::invalidCredentials();
        }

        $expiresAt = now()->addMinutes((int) config('rbac.session_minutes'));
        $token = $user->createToken('admin', ['*'], $expiresAt)->plainTextToken;

        $user->forceFill(['last_login_at' => now()])->save();

        return response()->json(['data' => [
            'token' => $token,
            'expires_at' => $expiresAt->toIso8601String(),
            'user' => $this->profile($user),
        ]]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['data' => ['message' => 'Signed out.']]);
    }

    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json(['data' => $this->profile($user)]);
    }

    /** @return array<string, mixed> */
    private function profile(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'roles' => $user->getRoleNames()->values(),
            'permissions' => $user->getAllPermissions()->pluck('name')->values(),
        ];
    }
}
