<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Mengarahkan setiap peran ke dashboard-nya masing-masing.
 *
 * Sengaja berupa controller, bukan closure, agar `route:cache` dapat dipakai
 * di produksi — rute closure tidak bisa diserialisasi.
 */
class HomeController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        return redirect()->route(match ($user->role) {
            User::ROLE_ADMIN => 'admin.dashboard',
            User::ROLE_ASDOS => 'asdos.dashboard',
            default => 'perwakilan.dashboard',
        });
    }
}
