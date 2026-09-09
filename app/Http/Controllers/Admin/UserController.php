<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Kelola akun Admin dan Perwakilan Kelas. Akun Asdos dibuat lewat menu Asdos
 * agar profil NIM/tim/kuota selalu ikut terisi.
 */
class UserController extends Controller
{
    public function index(Request $request): Response
    {
        $users = User::query()
            ->with('representedClasses:id,class_name,angkatan,representative_id')
            ->when($request->string('q')->toString(), fn ($q, $term) => $q
                ->where(fn ($w) => $w->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%")))
            ->when($request->string('role')->toString(), fn ($q, $role) => $q->where('role', $role))
            ->orderBy('role')
            ->orderBy('name')
            ->get()
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role,
                'is_active' => $user->is_active,
                'classes' => $user->representedClasses->map->label()->values(),
            ]);

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'filters' => [
                'q' => $request->string('q')->toString() ?: null,
                'role' => $request->string('role')->toString() ?: null,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', Rule::in([User::ROLE_ADMIN, User::ROLE_PERWAKILAN])],
            'is_active' => ['boolean'],
        ], [
            'role.in' => 'Akun Asdos dibuat melalui menu Asdos.',
        ], $this->attributeNames());

        User::query()->create($data);

        return back()->with('success', 'Pengguna berhasil ditambahkan.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user)],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['nullable', 'string', 'min:8'],
            // Role Asdos tidak boleh diubah dari sini agar profil Asdos tetap konsisten.
            'role' => ['required', Rule::in($user->isAsdos()
                ? [User::ROLE_ASDOS]
                : [User::ROLE_ADMIN, User::ROLE_PERWAKILAN])],
            'is_active' => ['boolean'],
        ], [], $this->attributeNames());

        if (blank($data['password'] ?? null)) {
            unset($data['password']);
        }

        $user->update($data);

        return back()->with('success', 'Pengguna berhasil diperbarui.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($user->is($request->user())) {
            return back()->with('error', 'Anda tidak dapat menghapus akun Anda sendiri.');
        }

        $user->delete();

        return back()->with('success', 'Pengguna berhasil dihapus.');
    }

    /** @return array<string, string> */
    private function attributeNames(): array
    {
        return [
            'name' => 'nama',
            'email' => 'email',
            'phone' => 'nomor HP',
            'password' => 'kata sandi',
            'role' => 'peran',
        ];
    }
}
