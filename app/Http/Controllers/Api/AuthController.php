<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required_without:otp|email|nullable',
            'password' => 'required_without:otp',
            'otp' => 'required_without:password',
        ]);

        if ($request->filled('otp')) {
            $secret = env('TOTP_SUPER_ADMIN_SECRET');
            if (! $secret) {
                return response()->json(['message' => 'Sistem belum dikonfigurasi untuk OTP (Secret tidak ditemukan).'], 500);
            }

            try {
                $google2fa = new Google2FA;
                $valid = $google2fa->verifyKey($secret, $request->otp);

                if (! $valid) {
                    return response()->json(['message' => 'Kode OTP Super Admin tidak valid.'], 422);
                }
            } catch (\Exception $e) {
                return response()->json(['message' => 'Terjadi kesalahan saat memverifikasi OTP.'], 500);
            }
            // Cari user pertama yang memiliki role admin
            $user = User::with(['role.permissions', 'employee'])->whereHas('role', function ($q) {
                $q->where('code', 'admin');
            })->first();

            if (! $user) {
                return response()->json(['message' => 'Akun Super Admin tidak ditemukan di sistem.'], 422);
            }
        } else {
            $user = User::with(['role.permissions', 'employee'])->where('email', $request->email)->first();

            if (! $user || ! Hash::check($request->password, $user->password) || $user->status !== 'active') {
                return response()->json([
                    'message' => 'Email atau kata sandi salah.',
                ], 422);
            }
        }

        $user->update(['last_login_at' => now()]);

        $user->tokens()->delete();

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'data' => [
                'token_type' => 'Bearer',
                'access_token' => $token,
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role ? [
                        'id' => $user->role->id,
                        'code' => $user->role->code,
                        'name' => $user->role->name,
                        'permissions' => $user->role->permissions,
                    ] : [
                        'id' => '0',
                        'code' => 'employee',
                        'name' => 'Karyawan',
                        'permissions' => [],
                    ],
                    'employee_id' => $user->employee->id ?? null,
                ],
            ],
        ]);
    }

    public function me(Request $request)
    {
        $user = $request->user()->load(['role.permissions', 'employee']);

        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role ? [
                    'id' => $user->role->id,
                    'code' => $user->role->code,
                    'name' => $user->role->name,
                    'permissions' => $user->role->permissions,
                ] : [
                    'id' => '0',
                    'code' => 'employee',
                    'name' => 'Karyawan',
                    'permissions' => [],
                ],
                'employee_id' => $user->employee->id ?? null,
            ],
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully.']);
    }

    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'name' => 'required|string|max:255',
            'password' => 'nullable|string|min:8|confirmed',
            'current_password' => 'required_with:password',
        ]);

        if ($request->filled('password')) {
            if (! Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'message' => 'Kata sandi saat ini tidak cocok.',
                ], 422);
            }

            $user->password = Hash::make($request->password);
        }

        $user->name = $request->name;
        $user->save();

        return response()->json([
            'message' => 'Profil berhasil diperbarui.',
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }
}
