<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->validated();

        $user = User::query()
            ->with(['role', 'employee'])
            ->where('email', $credentials['email'])
            ->first();

        if ($user === null || $user->status !== 'active' || ! Hash::check($credentials['password'], $user->password)) {
            return response()->json([
                'message' => 'Invalid credentials.',
            ], 422);
        }

        $token = Str::random(64);
        $user->forceFill([
            'remember_token' => hash('sha256', $token),
            'last_login_at' => now(),
        ])->save();

        return response()->json([
            'data' => [
                'token_type' => 'Bearer',
                'access_token' => $token,
                'user' => $user->fresh(['role', 'employee']),
            ],
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $this->userFromBearerToken($request);

        if ($user === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        return response()->json(['data' => $user]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $this->userFromBearerToken($request);

        if ($user === null) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $user->forceFill(['remember_token' => null])->save();

        return response()->json(['message' => 'Logged out.']);
    }

    private function userFromBearerToken(Request $request): ?User
    {
        $token = $request->bearerToken();

        if ($token === null || $token === '') {
            return null;
        }

        return User::query()
            ->with(['role', 'employee'])
            ->where('remember_token', hash('sha256', $token))
            ->first();
    }
}
