<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::query()->orderBy('name');

        if ($role = $request->query('role')) {
            $query->where('role', $role);
        }

        return response()->json([
            'success' => true,
            'data' => $query->get(['id', 'name', 'email', 'role', 'phone', 'created_at']),
        ]);
    }

    public function store(StoreUserRequest $request)
    {
        $user = User::create([
            'name' => $request->validated('name'),
            'email' => strtolower(trim($request->validated('email'))),
            'password' => Hash::make($request->validated('password')),
            'role' => $request->validated('role'),
            'phone' => $request->validated('phone'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Akun petugas berhasil dibuat.',
            'data' => $user,
        ], 201);
    }

    public function update(Request $request, User $user)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'role' => ['required', 'in:admin,scanner'],
            'phone' => ['nullable', 'string', 'max:20'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        $user->update(array_merge([
            'name' => $validated['name'],
            'email' => strtolower(trim($validated['email'])),
            'role' => $validated['role'],
            'phone' => $validated['phone'] ?? null,
        ], !empty($validated['password'])
            ? ['password' => Hash::make($validated['password'])]
            : []));

        return response()->json([
            'success' => true,
            'message' => 'Akun berhasil diperbarui.',
            'data' => $user->fresh(),
        ]);
    }

    public function destroy(Request $request, User $user)
    {
        if ($request->user()->id === $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak dapat menghapus akun Anda sendiri.',
            ], 422);
        }

        if ($user->isAdmin() && User::where('role', User::ROLE_ADMIN)->count() <= 1) {
            return response()->json([
                'success' => false,
                'message' => 'Minimal harus ada satu akun admin.',
            ], 422);
        }

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'Akun berhasil dihapus.',
        ]);
    }
}
