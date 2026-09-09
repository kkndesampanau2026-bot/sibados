<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Asdos;
use App\Models\Course;
use App\Models\User;
use App\Services\BookingSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AsdosController extends Controller
{
    public function index(Request $request): Response
    {
        $asdos = Asdos::query()
            ->withQuotaUsage()
            ->with(['user:id,name,email,phone,is_active', 'courses:id,name'])
            ->when($request->string('q')->toString(), fn ($q, $term) => $q
                ->where(fn ($w) => $w->where('nim', 'like', "%{$term}%")
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$term}%"))))
            ->get()
            ->sortBy(fn (Asdos $a) => $a->user?->name)
            ->values()
            ->map(fn (Asdos $a) => $this->present($a));

        return Inertia::render('Admin/Asdos/Index', [
            'asdos' => $asdos,
            'courses' => Course::query()->orderBy('semester')->orderBy('name')->get(['id', 'name', 'semester']),
            'filters' => ['q' => $request->string('q')->toString() ?: null],
            'defaultMaxClasses' => (int) app(BookingSettings::class)->get('default_max_classes'),
            'defaultCourseQuota' => (int) app(BookingSettings::class)->get('default_course_quota'),
        ]);
    }

    public function show(Asdos $asdos): Response
    {
        $asdos->load([
            'user:id,name,email,phone,is_active',
            'courses:id,name,semester',
        ]);

        // Booking aktif Asdos ini lewat seluruh pasangan yang memuatnya.
        $bookings = $asdos->activeBookings()
            ->with([
                'pair.asdosOne.user:id,name',
                'pair.asdosTwo.user:id,name',
                'practicum.course:id,name,semester',
                'practicum.classRoom.representative:id,name',
                'representative:id,name',
            ])
            ->get();

        return Inertia::render('Admin/Asdos/Show', [
            'asdos' => [
                'id' => $asdos->id,
                'name' => $asdos->user?->name,
                'nim' => $asdos->nim,
                'email' => $asdos->user?->email,
                'phone' => $asdos->user?->phone,
                'semester' => $asdos->semester,
                'status' => $asdos->status,
                'max_classes' => $asdos->max_classes,
                'used_quota' => $bookings->count(),
                'availability' => $asdos->availabilityStatus(),
                'courses' => $asdos->courses->map(fn ($c) => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'semester' => $c->semester,
                    'max' => (int) $c->pivot->max_practicums,
                    'used' => $asdos->usedQuotaForCourse($c->id),
                ]),
                'practicums' => $bookings->map(fn ($b) => [
                    'id' => $b->id,
                    'course' => $b->practicum?->course?->name,
                    'semester' => $b->practicum?->course?->semester,
                    'class_label' => $b->practicum?->classRoom?->label(),
                    'pair' => $b->pair?->label(),
                    'representative' => $b->representative?->name
                        ?? $b->practicum?->classRoom?->representative?->name,
                    'booked_at' => $b->booked_at?->translatedFormat('d M Y, H:i'),
                ])->values(),
            ],
        ]);
    }

    /** Membuat akun user + profil Asdos sekaligus. */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', 'unique:users,email'],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', 'string', 'min:8'],
            'nim' => ['required', 'string', 'max:30', 'unique:asdos,nim'],
            'semester' => ['nullable', 'string', 'max:10'],
            'max_classes' => ['required', 'integer', 'min:1', 'max:20'],
            'status' => ['required', Rule::in(['aktif', 'nonaktif'])],
            'course_ids' => ['array'],
            'course_ids.*' => ['exists:courses,id'],
            'course_quotas' => ['array'],
            'course_quotas.*' => ['integer', 'min:1', 'max:20'],
        ], [], $this->attributeNames());

        DB::transaction(function () use ($data) {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'password' => $data['password'],
                'role' => User::ROLE_ASDOS,
                'is_active' => true,
            ]);

            $asdos = Asdos::query()->create([
                'user_id' => $user->id,
                'nim' => $data['nim'],
                'semester' => $data['semester'] ?? null,
                'max_classes' => $data['max_classes'],
                'status' => $data['status'],
            ]);

            $asdos->courses()->sync($this->syncPayload($data));
        });

        return back()->with('success', 'Asdos berhasil ditambahkan.');
    }

    public function update(Request $request, Asdos $asdos): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($asdos->user_id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['nullable', 'string', 'min:8'],
            'nim' => ['required', 'string', 'max:30', Rule::unique('asdos', 'nim')->ignore($asdos)],
            'semester' => ['nullable', 'string', 'max:10'],
            'max_classes' => ['required', 'integer', 'min:1', 'max:20'],
            'status' => ['required', Rule::in(['aktif', 'nonaktif'])],
            'course_ids' => ['array'],
            'course_ids.*' => ['exists:courses,id'],
            'course_quotas' => ['array'],
            'course_quotas.*' => ['integer', 'min:1', 'max:20'],
        ], [], $this->attributeNames());

        DB::transaction(function () use ($data, $asdos) {
            $userData = [
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
            ];

            // Kata sandi hanya diganti bila Admin mengisinya.
            if (filled($data['password'] ?? null)) {
                $userData['password'] = $data['password'];
            }

            $asdos->user?->update($userData);

            $asdos->update([
                'nim' => $data['nim'],
                'semester' => $data['semester'] ?? null,
                'max_classes' => $data['max_classes'],
                'status' => $data['status'],
            ]);

            $asdos->courses()->sync($this->syncPayload($data));
        });

        return back()->with('success', 'Data Asdos berhasil diperbarui.');
    }

    public function destroy(Asdos $asdos): RedirectResponse
    {
        // Menghapus user akan menghapus profil Asdos beserta booking-nya (cascade).
        DB::transaction(fn () => $asdos->user?->delete() ?? $asdos->delete());

        return back()->with('success', 'Asdos berhasil dihapus.');
    }

    /** @return array<string, mixed> */
    private function present(Asdos $asdos): array
    {
        return [
            'id' => $asdos->id,
            'name' => $asdos->user?->name,
            'email' => $asdos->user?->email,
            'phone' => $asdos->user?->phone,
            'nim' => $asdos->nim,
            'semester' => $asdos->semester,
            'max_classes' => $asdos->max_classes,
            'used_quota' => $asdos->usedQuota(),
            'status' => $asdos->status,
            'availability' => $asdos->availabilityStatus(),
            'course_ids' => $asdos->courses->pluck('id'),
            'courses' => $asdos->courses->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'max' => (int) $c->pivot->max_practicums,
                'used' => $asdos->usedQuotaForCourse($c->id),
            ])->values(),
        ];
    }

    /**
     * Menyusun payload sync mata kuliah beserta kuota per mata kuliah.
     *
     * @param  array<string, mixed>  $data
     * @return array<int, array<string, int>>
     */
    private function syncPayload(array $data): array
    {
        $default = max(1, (int) app(BookingSettings::class)->get('default_course_quota'));
        $quotas = $data['course_quotas'] ?? [];
        $payload = [];

        foreach ($data['course_ids'] ?? [] as $id) {
            $payload[(int) $id] = [
                'max_practicums' => max(1, (int) ($quotas[$id] ?? $quotas[(string) $id] ?? $default)),
            ];
        }

        return $payload;
    }

    /** @return array<string, string> */
    private function attributeNames(): array
    {
        return [
            'name' => 'nama',
            'email' => 'email',
            'phone' => 'nomor HP',
            'password' => 'kata sandi',
            'nim' => 'NIM',
            'max_classes' => 'kuota total praktikum',
            'course_quotas' => 'kuota mata kuliah',
            'course_ids' => 'mata kuliah',
        ];
    }
}
