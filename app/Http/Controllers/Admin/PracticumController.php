<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClassRoom;
use App\Models\Practicum;
use App\Services\BookingSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Kelola praktikum (rombel x mata kuliah) milik sebuah rombel. */
class PracticumController extends Controller
{
    public function store(Request $request, ClassRoom $class): RedirectResponse
    {
        $data = $request->validate([
            'course_id' => [
                'required',
                'exists:courses,id',
                Rule::unique('practicums', 'course_id')->where('class_id', $class->id),
            ],
            'max_asdos' => ['nullable', 'integer', 'min:1', 'max:10'],
        ], [
            'course_id.unique' => 'Mata kuliah tersebut sudah menjadi praktikum kelas ini.',
        ], ['course_id' => 'mata kuliah', 'max_asdos' => 'kuota Asdos']);

        $class->practicums()->create([
            'course_id' => $data['course_id'],
            'max_asdos' => $data['max_asdos'] ?? max(1, (int) app(BookingSettings::class)->get('default_max_asdos')),
        ]);

        return back()->with('success', 'Praktikum berhasil ditambahkan.');
    }

    public function update(Request $request, Practicum $practicum): RedirectResponse
    {
        $data = $request->validate([
            'max_asdos' => ['required', 'integer', 'min:1', 'max:10'],
        ], [], ['max_asdos' => 'kuota Asdos']);

        if ($data['max_asdos'] < $practicum->activeBookings()->count()) {
            return back()->with('error', 'Kuota tidak boleh lebih kecil dari jumlah Asdos yang sudah dibooking.');
        }

        $practicum->update($data);

        return back()->with('success', 'Praktikum berhasil diperbarui.');
    }

    public function destroy(Practicum $practicum): RedirectResponse
    {
        $practicum->delete();

        return back()->with('success', 'Praktikum berhasil dihapus.');
    }

    /** Lock Booking: mengunci/membuka praktikum dari perubahan ketua kelas. */
    public function toggleLock(Practicum $practicum): RedirectResponse
    {
        $practicum->update(['is_locked' => ! $practicum->is_locked]);

        return back()->with('success', $practicum->is_locked
            ? 'Praktikum dikunci. Ketua kelas tidak dapat mengubah booking.'
            : 'Kunci dibuka. Ketua kelas dapat melakukan booking kembali.');
    }
}
