<?php

namespace App\Http\Controllers\Perwakilan;

use App\Exceptions\BookingException;
use App\Http\Controllers\Controller;
use App\Models\Asdos;
use App\Models\AsdosPair;
use App\Models\Booking;
use App\Models\ClassRoom;
use App\Models\Practicum;
use App\Services\BookingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Halaman booking untuk Ketua Kelas.
 *
 * Ketua kelas memimpin satu rombel dan memilih satu PASANGAN Asdos untuk setiap
 * praktikum yang diampu rombelnya — siapa cepat dia dapat.
 */
class BookingController extends Controller
{
    public function __construct(private readonly BookingService $bookings) {}

    public function index(Request $request): Response
    {
        $class = $this->classOf($request);

        if (! $class) {
            return Inertia::render('Perwakilan/Dashboard', [
                'classRoom' => null,
                'practicums' => [],
            ]);
        }

        $class->load([
            'practicums.course:id,name,semester',
            'practicums.activeBookings.pair.asdosOne.user:id,name',
            'practicums.activeBookings.pair.asdosTwo.user:id,name',
        ]);

        return Inertia::render('Perwakilan/Dashboard', [
            'classRoom' => [
                'id' => $class->id,
                'class_name' => $class->class_name,
                'angkatan' => $class->angkatan,
                'semester' => $class->semester,
                'label' => $class->label(),
                'total' => $class->practicums->count(),
                'filled' => $class->filledPracticums(),
            ],
            'practicums' => $class->practicums
                ->sortBy(fn (Practicum $p) => $p->course?->name)
                ->values()
                ->map(fn (Practicum $p) => [
                    'id' => $p->id,
                    'course' => $p->course?->name,
                    'semester' => $p->course?->semester,
                    'max_asdos' => $p->max_asdos,
                    'filled' => $p->activeBookings->count(),
                    'is_filled' => $p->isFilled(),
                    'is_locked' => $p->is_locked,
                    'pairs' => $p->activeBookings->map(fn (Booking $b) => [
                        'label' => $b->pair?->label(),
                        'members' => $b->pair?->members()->map(fn (Asdos $a) => $a->user?->name)->values() ?? [],
                        'booked_at' => $b->booked_at?->translatedFormat('d M Y, H:i'),
                    ])->values(),
                ]),
        ]);
    }

    /** Daftar pasangan Asdos yang boleh dipilih untuk satu praktikum. */
    public function show(Request $request, Practicum $practicum): Response
    {
        $this->authorizePracticum($request, $practicum);

        $practicum->load([
            'course:id,name,semester',
            'classRoom',
            'activeBookings.pair.asdosOne.user:id,name',
            'activeBookings.pair.asdosTwo.user:id,name',
        ]);

        // Pasangan hanya berlaku untuk mata kuliah tempat ia dibentuk.
        $candidates = AsdosPair::query()
            ->where('course_id', $practicum->course_id)
            ->with(['asdosOne.user:id,name,email,phone', 'asdosTwo.user:id,name,email,phone'])
            ->get();

        $usedPairIds = $practicum->activeBookings->pluck('pair_id')->all();

        return Inertia::render('Perwakilan/Booking', [
            'practicum' => [
                'id' => $practicum->id,
                'course' => $practicum->course?->name,
                'semester' => $practicum->course?->semester,
                'class_label' => $practicum->classRoom?->label(),
                'max_asdos' => $practicum->max_asdos,
                'filled' => $practicum->activeBookings->count(),
                'is_filled' => $practicum->activeBookings->count() >= $practicum->max_asdos,
                'is_locked' => $practicum->is_locked,
                'current' => $practicum->activeBookings->map(fn (Booking $b) => [
                    'label' => $b->pair?->label(),
                ])->values(),
            ],
            'pairs' => $candidates
                ->sortBy(fn (AsdosPair $p) => $p->label())
                ->values()
                ->map(fn (AsdosPair $p) => [
                    'id' => $p->id,
                    'label' => $p->label(),
                    'availability' => in_array($p->id, $usedPairIds, true)
                        ? 'sudah_dibooking'
                        : $p->availabilityStatus(),
                    'remaining' => $p->remainingQuota(),
                    'members' => $p->memberQuotaSummary(),
                ]),
        ]);
    }

    public function store(Request $request, Practicum $practicum): RedirectResponse
    {
        $this->authorizePracticum($request, $practicum);

        $data = $request->validate([
            'pair_id' => ['required', 'exists:asdos_pairs,id'],
        ], [], ['pair_id' => 'pasangan Asdos']);

        try {
            $this->bookings->book(
                $practicum,
                AsdosPair::query()->findOrFail($data['pair_id']),
                $request->user(),
            );
        } catch (BookingException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()
            ->route('perwakilan.dashboard')
            ->with('success', 'Booking berhasil. Pasangan Asdos telah ditempatkan pada praktikum kelas Anda.');
    }

    private function classOf(Request $request): ?ClassRoom
    {
        return ClassRoom::query()
            ->where('representative_id', $request->user()->id)
            ->first();
    }

    /** Ketua kelas hanya boleh mengurus praktikum rombelnya sendiri. */
    private function authorizePracticum(Request $request, Practicum $practicum): void
    {
        $practicum->loadMissing('classRoom');

        abort_unless(
            (int) $practicum->classRoom?->representative_id === (int) $request->user()->id,
            403,
            'Anda bukan ketua kelas untuk praktikum ini.',
        );
    }
}
