<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

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
            if ($request->otp !== 'SA-2026') {
                return response()->json(['message' => 'Kode OTP Super Admin tidak valid.'], 401);
            }
            // Cari user pertama yang memiliki role admin
            $user = User::with(['role', 'employee'])->whereHas('role', function($q) {
                $q->where('code', 'admin');
            })->first();

            if (!$user) {
                return response()->json(['message' => 'Akun Super Admin tidak ditemukan di sistem.'], 401);
            }
        } else {
            $user = User::with(['role', 'employee'])->where('email', $request->email)->first();

            if (!$user || !Hash::check($request->password, $user->password)) {
                return response()->json([
                    'message' => 'Email atau kata sandi salah.'
                ], 401);
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
                    ] : [
                        'id' => '0',
                        'code' => 'employee',
                        'name' => 'Karyawan'
                    ],
                    'employee_id' => $user->employee->id ?? null
                ]
            ]
        ]);
    }

    public function me(Request $request)
    {
        $user = $request->user()->load(['role', 'employee']);
        
        return response()->json([
            'data' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role ? [
                    'id' => $user->role->id,
                    'code' => $user->role->code,
                    'name' => $user->role->name,
                ] : [
                    'id' => '0',
                    'code' => 'employee',
                    'name' => 'Karyawan'
                ],
                'employee_id' => $user->employee->id ?? null
            ]
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Logged out successfully.']);
    }
}
