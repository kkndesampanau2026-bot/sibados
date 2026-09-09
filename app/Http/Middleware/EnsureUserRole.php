<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Role-based access control (PRD pasal 26). Dipakai sebagai middleware "role:admin". */
class EnsureUserRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! in_array($user->role, $roles, true)) {
            abort(403, 'Anda tidak memiliki akses ke halaman ini.');
        }

        if (! $user->is_active) {
            abort(403, 'Akun Anda dinonaktifkan. Hubungi Koordinator.');
        }

        return $next($request);
    }
}
