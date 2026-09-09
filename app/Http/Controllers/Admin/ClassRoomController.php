<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\ClassRoom;
use App\Models\Course;
use App\Models\Practicum;
use App\Models\User;
use App\Services\BookingSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Kelola rombel (kelas) beserta praktikum yang diampunya. */
class ClassRoomController extends Controller
{
    public function index(Request $request): Response
    {
        $classes = ClassRoom::query()
            ->with([
                'representative:id,name,phone',
                'practicums.course:id,name,semester',
                'practicums' => fn ($q) => $q->withCount([
                    'bookings as active_bookings_count' => fn ($b) => $b->where('status', Booking::STATUS_AKTIF),
                ]),
            ])
            ->when($request->integer('angkatan'), fn ($q, $a) => $q->where('angkatan', $a))
            ->orderByDesc('angkatan')
            ->orderBy('class_name')
            ->get()
            ->map(fn (ClassRoom $c) => [
                'id' => $c->id,
                'class_name' => $c->class_name,
                'angkatan' => $c->angkatan,
                'semester' => $c->semester,
                'label' => $c->label(),
                'representative' => $c->representative?->name,
                'representative_id' => $c->representative_id,
                'representative_phone' => $c->representative?->phone,
                'total_practicums' => $c->practicums->count(),
                'filled_practicums' => $c->filledPracticums(),
            ]);

        return Inertia::render('Admin/Classes/Index', [
            'classes' => $classes,
            'angkatanList' => ClassRoom::query()->distinct()->orderByDesc('angkatan')->pluck('angkatan'),
            'representatives' => $this->availableRepresentatives(),
            'filters' => ['angkatan' => $request->integer('angkatan') ?: null],
            'defaultMaxAsdos' => (int) app(BookingSettings::class)->get('default_max_asdos'),
        ]);
    }

    /** Detail rombel: daftar praktikum + Asdos yang sudah dibooking. */
    public function show(ClassRoom $class): Response
    {
        $class->load([
            'representative:id,name,email,phone',
            'practicums.course:id,name,semester',
            'practicums.activeBookings.pair.asdosOne.user:id,name',
            'practicums.activeBookings.pair.asdosTwo.user:id,name',
        ]);

        return Inertia::render('Admin/Classes/Show', [
            'classRoom' => [
                'id' => $class->id,
                'class_name' => $class->class_name,
                'angkatan' => $class->angkatan,
                'semester' => $class->semester,
                'label' => $class->label(),
                'representative' => $class->representative?->name,
                'representative_email' => $class->representative?->email,
                'representative_phone' => $class->representative?->phone,
            ],
            'practicums' => $class->practicums
                ->sortBy(fn (Practicum $p) => $p->course?->name)
                ->values()
                ->map(fn (Practicum $p) => [
                    'id' => $p->id,
                    'course' => $p->course?->name,
                    'course_id' => $p->course_id,
                    'semester' => $p->course?->semester,
                    'max_asdos' => $p->max_asdos,
                    'is_locked' => $p->is_locked,
                    'is_filled' => $p->isFilled(),
                    'asdos' => $p->activeBookings->map(fn (Booking $b) => [
                        'booking_id' => $b->id,
                        'label' => $b->pair?->label(),
                        'booked_at' => $b->booked_at?->translatedFormat('d M Y, H:i'),
                    ])->values(),
                ]),
            // Mata kuliah yang belum menjadi praktikum rombel ini.
            'availableCourses' => Course::query()
                ->aktif()
                ->whereNotIn('id', $class->practicums->pluck('course_id'))
                ->orderBy('semester')
                ->orderBy('name')
                ->get(['id', 'name', 'semester']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);

        $class = ClassRoom::query()->create($data);

        // Langsung buatkan praktikum untuk seluruh mata kuliah pada semester itu.
        $created = $this->syncPracticums($class);

        return back()->with('success', sprintf(
            'Kelas berhasil ditambahkan dengan %d praktikum.',
            $created,
        ));
    }

    public function update(Request $request, ClassRoom $class): RedirectResponse
    {
        $class->update($this->validated($request, $class));

        return back()->with('success', 'Kelas berhasil diperbarui.');
    }

    public function destroy(ClassRoom $class): RedirectResponse
    {
        // Praktikum dan booking ikut terhapus lewat cascade.
        $class->delete();

        return back()->with('success', 'Kelas berhasil dihapus.');
    }

    /** Menambahkan praktikum untuk seluruh mata kuliah aktif pada semester rombel. */
    public function sync(ClassRoom $class): RedirectResponse
    {
        $created = $this->syncPracticums($class);

        return back()->with('success', $created > 0
            ? "{$created} praktikum baru ditambahkan."
            : 'Semua mata kuliah semester ini sudah terdaftar sebagai praktikum.');
    }

    /**
     * Membuat praktikum untuk tiap mata kuliah aktif yang semesternya cocok.
     *
     * @return int Jumlah praktikum baru.
     */
    private function syncPracticums(ClassRoom $class): int
    {
        $existing = $class->practicums()->pluck('course_id');

        $courses = Course::query()
            ->aktif()
            ->where('semester', $class->semester)
            ->whereNotIn('id', $existing)
            ->get();

        $defaultMaxAsdos = (int) app(BookingSettings::class)->get('default_max_asdos');

        foreach ($courses as $course) {
            $class->practicums()->create([
                'course_id' => $course->id,
                'max_asdos' => max(1, $defaultMaxAsdos),
            ]);
        }

        return $courses->count();
    }

    /**
     * Perwakilan yang belum menjadi ketua rombel lain (satu ketua = satu rombel).
     */
    private function availableRepresentatives(): Collection
    {
        return User::query()
            ->where('role', User::ROLE_PERWAKILAN)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->values();
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, ?ClassRoom $class = null): array
    {
        return $request->validate([
            'class_name' => [
                'required', 'string', 'max:10',
                Rule::unique('classes', 'class_name')
                    ->where('angkatan', $request->integer('angkatan'))
                    ->ignore($class),
            ],
            'angkatan' => ['required', 'integer', 'min:2000', 'max:2100'],
            'semester' => ['required', 'string', 'max:10'],
            'representative_id' => [
                'nullable',
                Rule::exists('users', 'id')->where('role', User::ROLE_PERWAKILAN),
                // Satu ketua kelas hanya boleh memimpin satu rombel.
                Rule::unique('classes', 'representative_id')->ignore($class),
            ],
        ], [
            'class_name.unique' => 'Kelas dengan nama tersebut sudah ada pada angkatan ini.',
            'representative_id.unique' => 'Mahasiswa tersebut sudah menjadi ketua kelas lain.',
        ], [
            'class_name' => 'nama kelas',
            'angkatan' => 'angkatan',
            'semester' => 'semester',
            'representative_id' => 'ketua kelas',
        ]);
    }
}
