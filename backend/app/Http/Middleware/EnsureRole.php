<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureRole
{
    /**
     * Allow only the given roles. Usage: ->middleware('role:admin')
     * or ->middleware('role:admin,scanner').
     */
    public function handle(Request $request, Closure $next, string ...$roles)
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Silakan login terlebih dahulu.',
            ], 401);
        }

        if (!in_array($user->role, $roles, true)) {
            return response()->json([
                'success' => false,
                'message' => 'Akses ditolak. Anda tidak memiliki izin untuk tindakan ini.',
            ], 403);
        }

        return $next($request);
    }
}
