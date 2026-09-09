<?php

namespace App\Http\Controllers;

use App\Models\Asdos;
use App\Models\Booking;
use App\Models\ClassRoom;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Halaman profil untuk Asisten Dosen dan Ketua Kelas.
 *
 * Pengguna hanya boleh mengubah identitas kontaknya sendiri. Data yang menjadi
 * kewenangan Koordinator — NIM, kuota, pasangan, dan penugasan kelas — bersifat
 * baca saja di sini.
 */
class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Profile/Edit', [
            'profile' => [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role,
                'is_active' => $user->is_active,
            ],
            'context' => $user->isAsdos()
                ? $this->asdosContext($user)
                : $this->representativeContext($user),
        ]);
    }

    /** Menyimpan perubahan identitas kontak. */
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user)],
            'phone' => ['nullable', 'string', 'max:30'],
        ], [], [
            'name' => 'nama',
            'email' => 'email',
            'phone' => 'nomor HP',
        ]);

        $user->update($data);

        return back()->with('success', 'Profil berhasil diperbarui.');
    }

    /** Mengganti kata sandi; kata sandi lama wajib dicocokkan lebih dulu. */
    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [
            'current_password.current_password' => 'Kata sandi saat ini tidak sesuai.',
            'password.confirmed' => 'Konfirmasi kata sandi tidak cocok.',
        ], [
            'current_password' => 'kata sandi saat ini',
            'password' => 'kata sandi baru',
        ]);

        $request->user()->update(['password' => $data['password']]);

        return back()->with('success', 'Kata sandi berhasil diganti.');
    }

    /**
     * Data baca-saja milik Asdos: identitas akademik, pasangan, dan kuota.
     *
     * @return array<string, mixed>
     */
    private function asdosContext(User $user): array
    {
        $asdos = $user->asdos;

        if (! $asdos) {
            return ['type' => 'asdos', 'ready' => false];
        }

        $asdos->load('courses:id,name,semester');

        $pairs = $asdos->pairs()
            ->with(['course:id,name,semester', 'asdosOne.user:id,name', 'asdosTwo.user:id,name'])
            ->get();

        $bookings = $asdos->activeBookings()
            ->with(['practicum.course:id,name', 'practicum.classRoom:id,class_name,angkatan,semester'])
            ->get();

        return [
            'type' => 'asdos',
            'ready' => true,
            'nim' => $asdos->nim,
            'semester' => $asdos->semester,
            'status' => $asdos->status,
            'availability' => $asdos->availabilityStatus(),
            'used_quota' => $bookings->count(),
            'max_classes' => $asdos->max_classes,
            'courses' => $asdos->courses->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'semester' => $c->semester,
                'max' => (int) $c->pivot->max_practicums,
                'used' => $asdos->usedQuotaForCourse($c->id),
            ])->values(),
            'pairs' => $pairs->map(fn ($pair) => [
                'id' => $pair->id,
                'course' => $pair->course?->name,
                'partner' => $pair->members()->first(fn (Asdos $m) => $m->id !== $asdos->id)?->user?->name,
                'status' => $pair->status,
            ])->values(),
            'practicums' => $bookings->map(fn (Booking $b) => [
                'id' => $b->id,
                'course' => $b->practicum?->course?->name,
                'class_label' => $b->practicum?->classRoom?->label(),
            ])->values(),
        ];
    }

    /**
     * Data baca-saja milik Ketua Kelas: rombel yang dipimpin dan progresnya.
     *
     * @return array<string, mixed>
     */
    private function representativeContext(User $user): array
    {
        $class = ClassRoom::query()
            ->where('representative_id', $user->id)
            ->with(['practicums.course:id,name,semester'])
            ->first();

        if (! $class) {
            return ['type' => 'perwakilan', 'ready' => false];
        }

        return [
            'type' => 'perwakilan',
            'ready' => true,
            'label' => $class->label(),
            'class_name' => $class->class_name,
            'angkatan' => $class->angkatan,
            'semester' => $class->semester,
            'total' => $class->practicums->count(),
            'filled' => $class->filledPracticums(),
            'practicums' => $class->practicums
                ->sortBy(fn ($p) => $p->course?->name)
                ->values()
                ->map(fn ($p) => [
                    'id' => $p->id,
                    'course' => $p->course?->name,
                    'semester' => $p->course?->semester,
                    'is_filled' => $p->isFilled(),
                ]),
        ];
    }
}
