<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Asdos;
use App\Models\AsdosPair;
use App\Models\Booking;
use App\Models\Course;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Kelola Pasangan Asdos. Koordinator membentuk pasangan untuk tiap mata kuliah;
 * pasangan inilah yang dipilih ketua kelas saat booking.
 */
class AsdosPairController extends Controller
{
    public function index(Request $request): Response
    {
        $courses = Course::query()
            ->orderBy('semester')
            ->orderBy('name')
            ->get(['id', 'name', 'semester']);

        $pairs = AsdosPair::query()
            ->with([
                'course:id,name,semester',
                'asdosOne.user:id,name,email,phone',
                'asdosTwo.user:id,name,email,phone',
            ])
            ->withCount(['bookings as active_bookings_count' => fn ($q) => $q->where('status', Booking::STATUS_AKTIF)])
            ->when($request->integer('course_id'), fn ($q, $id) => $q->where('course_id', $id))
            ->get()
            ->sortBy(fn (AsdosPair $p) => [$p->course?->semester, $p->course?->name, $p->label()])
            ->values()
            ->map(fn (AsdosPair $p) => $this->present($p));

        return Inertia::render('Admin/Pairs/Index', [
            'pairs' => $pairs,
            'courses' => $courses,
            // Kandidat anggota per mata kuliah: Asdos yang ditugaskan pada matkul itu.
            'asdosByCourse' => $this->eligibleAsdosByCourse(),
            'filters' => ['course_id' => $request->integer('course_id') ?: null],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        AsdosPair::query()->create([
            'course_id' => $data['course_id'],
            'status' => $data['status'],
            ...AsdosPair::normalizeMembers((int) $data['asdos_one_id'], (int) $data['asdos_two_id']),
        ]);

        return back()->with('success', 'Pasangan Asdos berhasil dibentuk.');
    }

    public function update(Request $request, AsdosPair $pair): RedirectResponse
    {
        $data = $this->validated($request, $pair);

        $members = AsdosPair::normalizeMembers((int) $data['asdos_one_id'], (int) $data['asdos_two_id']);

        // Mengganti anggota saat pasangan sedang mengampu praktikum berisiko
        // membuat kuota individu meleset, jadi minta Admin membatalkan dulu.
        $activeCount = $pair->activeBookings()->count();
        $memberChanged = $members['asdos_one_id'] !== $pair->asdos_one_id
            || $members['asdos_two_id'] !== $pair->asdos_two_id
            || (int) $data['course_id'] !== (int) $pair->course_id;

        if ($activeCount > 0 && $memberChanged) {
            return back()->with('error', sprintf(
                'Pasangan ini sedang mengampu %d praktikum. Batalkan booking tersebut sebelum mengubah anggota atau mata kuliah.',
                $activeCount,
            ));
        }

        $pair->update([
            'course_id' => $data['course_id'],
            'status' => $data['status'],
            ...$members,
        ]);

        return back()->with('success', 'Pasangan Asdos berhasil diperbarui.');
    }

    public function destroy(AsdosPair $pair): RedirectResponse
    {
        if ($pair->activeBookings()->count() > 0) {
            return back()->with('error', 'Pasangan ini masih mengampu praktikum. Batalkan booking-nya terlebih dahulu.');
        }

        $pair->delete();

        return back()->with('success', 'Pasangan Asdos berhasil dihapus.');
    }

    /** @return array<string, mixed> */
    private function present(AsdosPair $pair): array
    {
        return [
            'id' => $pair->id,
            'course_id' => $pair->course_id,
            'course' => $pair->course?->name,
            'semester' => $pair->course?->semester,
            'label' => $pair->label(),
            'status' => $pair->status,
            'availability' => $pair->availabilityStatus(),
            'active_bookings' => (int) ($pair->active_bookings_count ?? 0),
            'members' => $pair->memberQuotaSummary(),
            'asdos_one_id' => $pair->asdos_one_id,
            'asdos_two_id' => $pair->asdos_two_id,
        ];
    }

    /**
     * Peta course_id => Asdos aktif yang ditugaskan pada mata kuliah tersebut.
     *
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function eligibleAsdosByCourse(): array
    {
        $map = [];

        $all = Asdos::query()
            ->aktif()
            ->withQuotaUsage()
            ->with(['user:id,name', 'courses:id'])
            ->get();

        foreach ($all as $asdos) {
            foreach ($asdos->courses as $course) {
                $map[$course->id][] = [
                    'id' => $asdos->id,
                    'name' => $asdos->user?->name,
                    'nim' => $asdos->nim,
                    'course_used' => $asdos->usedQuotaForCourse($course->id),
                    'course_max' => (int) $course->pivot->max_practicums,
                    'used_quota' => $asdos->usedQuota(),
                    'max_classes' => $asdos->max_classes,
                ];
            }
        }

        foreach ($map as $courseId => $list) {
            usort($list, fn ($a, $b) => strcmp((string) $a['name'], (string) $b['name']));
            $map[$courseId] = $list;
        }

        return $map;
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?AsdosPair $pair = null): array
    {
        $data = $request->validate([
            'course_id' => ['required', 'exists:courses,id'],
            'asdos_one_id' => ['required', 'exists:asdos,id', 'different:asdos_two_id'],
            'asdos_two_id' => ['required', 'exists:asdos,id'],
            'status' => ['required', Rule::in(['aktif', 'nonaktif'])],
        ], [
            'asdos_one_id.different' => 'Pasangan harus terdiri dari dua Asdos yang berbeda.',
        ], [
            'course_id' => 'mata kuliah',
            'asdos_one_id' => 'Asdos pertama',
            'asdos_two_id' => 'Asdos kedua',
        ]);

        $courseId = (int) $data['course_id'];
        $members = AsdosPair::normalizeMembers((int) $data['asdos_one_id'], (int) $data['asdos_two_id']);

        // Kedua anggota harus ditugaskan pada mata kuliah tersebut.
        foreach ([$data['asdos_one_id'], $data['asdos_two_id']] as $field => $asdosId) {
            $assigned = Asdos::query()
                ->whereKey($asdosId)
                ->whereHas('courses', fn ($q) => $q->where('courses.id', $courseId))
                ->exists();

            if (! $assigned) {
                throw ValidationException::withMessages([
                    'asdos_one_id' => 'Kedua Asdos harus ditugaskan pada mata kuliah ini terlebih dahulu melalui menu Penempatan Matkul.',
                ]);
            }
        }

        $duplicate = AsdosPair::query()
            ->where('course_id', $courseId)
            ->where('asdos_one_id', $members['asdos_one_id'])
            ->where('asdos_two_id', $members['asdos_two_id'])
            ->when($pair, fn ($q) => $q->whereKeyNot($pair->getKey()))
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'asdos_one_id' => 'Pasangan tersebut sudah terdaftar pada mata kuliah ini.',
            ]);
        }

        return $data;
    }
}
