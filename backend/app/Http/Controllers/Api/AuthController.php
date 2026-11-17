<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(LoginRequest $request)
    {
        $credentials = $request->validated();

        $user = User::where('email', strtolower(trim($credentials['email'])))->first();

        if (!$user || !Hash::check($credentials['password'], $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Email atau password yang Anda masukkan salah.',
                'errors' => [
                    'email' => ['Kredensial yang diberikan tidak cocok dengan data kami.'],
                ],
            ], 422);
        }

        if (!in_array($user->role, [User::ROLE_ADMIN, User::ROLE_SCANNER], true)) {
            return response()->json([
                'success' => false,
                'message' => 'Akun ini tidak memiliki akses ke aplikasi.',
            ], 403);
        }

        $user->tokens()->delete();
        $token = $user->createToken('invitaku_token')->plainTextToken;

        return response()->json([
            'success' => true,
            'message' => 'Login berhasil. Selamat datang kembali!',
            'data' => [
                'user' => $this->userPayload($user),
                'token' => $token,
            ],
        ]);
    }

    public function logout(Request $request)
    {
        if ($request->user()) {
            $request->user()->currentAccessToken()->delete();
        }

        return response()->json([
            'success' => true,
            'message' => 'Berhasil logout.',
        ]);
    }

    public function me(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => ['user' => $this->userPayload($request->user())],
        ]);
    }

    public function updateProfile(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
        ]);

        $request->user()->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Profil berhasil diperbarui.',
            'data' => ['user' => $this->userPayload($request->user())],
        ]);
    }

    public function updatePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'new_password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = $request->user();

        if (!Hash::check($validated['current_password'], $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Password saat ini tidak sesuai.',
                'errors' => ['current_password' => ['Password saat ini tidak sesuai.']],
            ], 422);
        }

        $user->update(['password' => Hash::make($validated['new_password'])]);

        return response()->json([
            'success' => true,
            'message' => 'Password berhasil diubah.',
        ]);
    }

    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'phone' => $user->phone,
            'avatar' => $user->avatar,
            'created_at' => $user->created_at,
        ];
    }
}
