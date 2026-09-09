<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Asdos;
use App\Models\Booking;
use App\Models\Course;
use App\Services\BookingSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Penempatan Mata Kuliah Asdos — menentukan Asdos mana yang boleh menangani
 * setiap mata kuliah, sekaligus KUOTA per mata kuliah: berapa praktikum pada
 * mata kuliah itu yang boleh diampu Asdos tersebut. Kuota inilah yang membatasi
 * berapa kelas dapat memilih Asdos yang sama.
 */
class AssignmentController extends Controller
{
    public function index(): Response
    {
        $usage = $this->usageByAsdosAndCourse();

        $asdos = Asdos::query()
            ->withQuotaUsage()
            ->with(['user:id,name', 'courses:id'])
            ->get()
            ->sortBy(fn (Asdos $a) => $a->user?->name)
            ->values()
            ->map(fn (Asdos $a) => [
                'id' => $a->id,
                'name' => $a->user?->name,
                'nim' => $a->nim,
                'status' => $a->status,
                'max_classes' => $a->max_classes,
                'used_quota' => $a->usedQuota(),
                'availability' => $a->availabilityStatus(),
                // course_id => ['max' => kuota, 'used' => terpakai]
                'courses' => $a->courses->mapWithKeys(fn ($c) => [
                    $c->id => [
                        'max' => (int) $c->pivot->max_practicums,
                        'used' => $usage[$a->id][$c->id] ?? 0,
                    ],
                ]),
            ]);

        return Inertia::render('Admin/Assignments/Index', [
            'asdos' => $asdos,
            'courses' => Course::query()
                ->orderBy('semester')
                ->orderBy('name')
                ->get(['id', 'name', 'semester']),
            'defaultCourseQuota' => $this->defaultQuota(),
        ]);
    }

    /** Menyimpan daftar mata kuliah beserta kuotanya untuk satu Asdos. */
    public function update(Request $request, Asdos $asdos): RedirectResponse
    {
        $data = $request->validate([
            'course_ids' => ['array'],
            'course_ids.*' => ['exists:courses,id'],
            'course_quotas' => ['array'],
            'course_quotas.*' => ['integer', 'min:1', 'max:20'],
            'max_classes' => ['required', 'integer', 'min:1', 'max:20'],
        ], [], [
            'course_ids' => 'mata kuliah',
            'course_quotas' => 'kuota mata kuliah',
            'max_classes' => 'kuota total',
        ]);

        $asdos->courses()->sync($this->syncPayload($data['course_ids'] ?? [], $data['course_quotas'] ?? []));
        $asdos->update(['max_classes' => $data['max_classes']]);

        return back()->with('success', 'Penempatan mata kuliah berhasil disimpan.');
    }

    /** Menyalakan/mematikan satu pasangan Asdos-Mata Kuliah dari tampilan matriks. */
    public function toggle(Request $request, Asdos $asdos): RedirectResponse
    {
        $data = $request->validate([
            'course_id' => ['required', 'exists:courses,id'],
        ], [], ['course_id' => 'mata kuliah']);

        $courseId = (int) $data['course_id'];

        if ($asdos->courses()->whereKey($courseId)->exists()) {
            // Menolak pencabutan bila Asdos masih mengampu praktikum matkul itu.
            if ($asdos->usedQuotaForCourse($courseId) > 0) {
                return back()->with('error', 'Asdos masih mengampu praktikum pada mata kuliah ini. Batalkan booking-nya terlebih dahulu.');
            }

            $asdos->courses()->detach($courseId);

            return back()->with('success', 'Penempatan mata kuliah dicabut.');
        }

        $asdos->courses()->attach($courseId, ['max_practicums' => $this->defaultQuota()]);

        return back()->with('success', 'Penempatan mata kuliah ditambahkan.');
    }

    /** Mengubah kuota Asdos pada satu mata kuliah dari tampilan matriks. */
    public function quota(Request $request, Asdos $asdos): RedirectResponse
    {
        $data = $request->validate([
            'course_id' => ['required', 'exists:courses,id'],
            'max_practicums' => ['required', 'integer', 'min:1', 'max:20'],
        ], [], ['course_id' => 'mata kuliah', 'max_practicums' => 'kuota']);

        $courseId = (int) $data['course_id'];

        if (! $asdos->courses()->whereKey($courseId)->exists()) {
            return back()->with('error', 'Asdos belum ditugaskan pada mata kuliah ini.');
        }

        $used = $asdos->usedQuotaForCourse($courseId);

        if ($data['max_practicums'] < $used) {
            return back()->with('error', sprintf(
                'Kuota tidak boleh lebih kecil dari %d praktikum yang sedang diampu.',
                $used,
            ));
        }

        $asdos->courses()->updateExistingPivot($courseId, ['max_practicums' => $data['max_practicums']]);

        return back()->with('success', 'Kuota mata kuliah diperbarui.');
    }

    /**
     * Menyusun payload sync dengan kuota per mata kuliah.
     *
     * @param  array<int, int|string>  $courseIds
     * @param  array<int|string, int>  $quotas
     * @return array<int, array<string, int>>
     */
    private function syncPayload(array $courseIds, array $quotas): array
    {
        $default = $this->defaultQuota();
        $payload = [];

        foreach ($courseIds as $id) {
            $payload[(int) $id] = [
                'max_practicums' => max(1, (int) ($quotas[$id] ?? $quotas[(string) $id] ?? $default)),
            ];
        }

        return $payload;
    }

    private function defaultQuota(): int
    {
        return max(1, (int) app(BookingSettings::class)->get('default_course_quota'));
    }

    /**
     * Jumlah praktikum aktif tiap Asdos per mata kuliah dalam satu query.
     *
     * @return array<int, array<int, int>> [asdos_id][course_id] => jumlah
     */
    private function usageByAsdosAndCourse(): array
    {
        $rows = DB::table('bookings')
            ->join('asdos_pairs', 'asdos_pairs.id', '=', 'bookings.pair_id')
            ->where('bookings.status', Booking::STATUS_AKTIF)
            ->get(['asdos_pairs.course_id', 'asdos_pairs.asdos_one_id', 'asdos_pairs.asdos_two_id']);

        $usage = [];

        foreach ($rows as $row) {
            foreach ([$row->asdos_one_id, $row->asdos_two_id] as $asdosId) {
                if ($asdosId === null) {
                    continue;
                }

                $usage[$asdosId][$row->course_id] = ($usage[$asdosId][$row->course_id] ?? 0) + 1;
            }
        }

        return $usage;
    }
}
