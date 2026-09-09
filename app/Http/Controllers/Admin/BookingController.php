<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\BookingException;
use App\Exports\BookingsExport;
use App\Http\Controllers\Controller;
use App\Models\Asdos;
use App\Models\AsdosPair;
use App\Models\Booking;
use App\Models\BookingLog;
use App\Models\Course;
use App\Models\Practicum;
use App\Services\BookingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BookingController extends Controller
{
    public function __construct(private readonly BookingService $bookings) {}

    /** Daftar seluruh booking + penempatan (menu Booking & Penempatan Asdos). */
    public function index(Request $request): Response
    {
        $status = $request->string('status')->toString() ?: 'aktif';

        $bookings = Booking::query()
            ->with([
                'practicum.course:id,name,semester',
                'practicum.classRoom:id,class_name,angkatan,semester',
                'pair.asdosOne.user:id,name',
                'pair.asdosTwo.user:id,name',
                'representative:id,name',
                'canceller:id,name',
            ])
            ->when($status !== 'semua', fn ($q) => $q->where('status', $status))
            ->when($request->integer('course_id'), fn ($q, $id) => $q
                ->whereHas('practicum', fn ($p) => $p->where('course_id', $id)))
            ->latest('booked_at')
            ->latest('id')
            ->get()
            ->map(fn (Booking $b) => [
                'id' => $b->id,
                'course' => $b->practicum?->course?->name,
                'course_id' => $b->practicum?->course_id,
                'semester' => $b->practicum?->course?->semester,
                'practicum_id' => $b->practicum_id,
                'class_label' => $b->practicum?->classRoom?->label(),
                'pair_id' => $b->pair_id,
                'pair' => $b->pair?->label(),
                'members' => $b->pair?->members()->map(fn (Asdos $a) => $a->user?->name)->values() ?? [],
                'representative' => $b->representative?->name,
                'status' => $b->status,
                'booked_at' => $b->booked_at?->translatedFormat('d M Y, H:i'),
                'cancelled_at' => $b->cancelled_at?->translatedFormat('d M Y, H:i'),
                'cancelled_by' => $b->canceller?->name,
                'note' => $b->note,
            ]);

        return Inertia::render('Admin/Bookings/Index', [
            'bookings' => $bookings,
            'filters' => [
                'status' => $status,
                'course_id' => $request->integer('course_id') ?: null,
            ],
            'courses' => Course::query()->orderBy('semester')->orderBy('name')->get(['id', 'name', 'semester']),
            'practicums' => $this->bookablePracticums(),
            'pairsByCourse' => $this->pairsByCourse(),
        ]);
    }

    /**
     * Mengunduh data booking sebagai berkas Excel, mengikuti penyaring yang
     * sedang aktif pada halaman.
     */
    public function export(Request $request): BinaryFileResponse
    {
        $status = $request->string('status')->toString() ?: 'aktif';
        $courseId = $request->integer('course_id') ?: null;

        $label = match ($status) {
            'dibatalkan' => 'dibatalkan',
            'semua' => 'semua',
            default => 'aktif',
        };

        $filename = sprintf('booking-asdos-%s-%s.xlsx', $label, now()->format('Ymd-Hi'));

        return Excel::download(new BookingsExport($status, $courseId), $filename);
    }

    /** Praktikum Belum Terisi. */
    public function emptyPracticums(): Response
    {
        $practicums = Practicum::query()
            ->with(['course:id,name,semester', 'classRoom.representative:id,name'])
            ->withCount(['bookings as active_bookings_count' => fn ($q) => $q->where('status', Booking::STATUS_AKTIF)])
            ->get()
            ->reject->isFilled()
            ->sortBy(fn (Practicum $p) => [
                -(int) ($p->classRoom?->angkatan ?? 0),
                $p->classRoom?->class_name ?? '',
                $p->course?->name ?? '',
            ])
            ->values()
            ->map(fn (Practicum $p) => [
                'id' => $p->id,
                'course_id' => $p->course_id,
                'course' => $p->course?->name,
                'semester' => $p->course?->semester,
                'class_label' => $p->classRoom?->label(),
                'representative' => $p->classRoom?->representative?->name,
                'filled' => $p->filledSlots(),
                'max_asdos' => $p->max_asdos,
                'is_locked' => $p->is_locked,
            ]);

        return Inertia::render('Admin/Bookings/EmptyPracticums', [
            'practicums' => $practicums,
            'pairsByCourse' => $this->pairsByCourse(),
        ]);
    }

    /** Riwayat Booking. */
    public function history(Request $request): Response
    {
        $logs = BookingLog::query()
            ->with(['user:id,name'])
            ->when($request->string('action')->toString(), fn ($q, $a) => $q->where('action', $a))
            ->latest()
            ->paginate(25)
            ->withQueryString()
            ->through(fn (BookingLog $log) => [
                'id' => $log->id,
                'at' => $log->created_at?->translatedFormat('d M Y, H:i:s'),
                'user' => $log->user?->name ?? 'Sistem',
                'action' => $log->action,
                'old_value' => $log->old_value,
                'new_value' => $log->new_value,
                'description' => $log->description,
                'ip_address' => $log->ip_address,
            ]);

        return Inertia::render('Admin/Bookings/History', [
            'logs' => $logs,
            'filters' => ['action' => $request->string('action')->toString() ?: null],
            'actions' => BookingLog::query()->distinct()->orderBy('action')->pluck('action'),
        ]);
    }

    /** Admin menempatkan pasangan secara manual (mengabaikan jadwal & lock). */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'practicum_id' => ['required', 'exists:practicums,id'],
            'pair_id' => ['required', 'exists:asdos_pairs,id'],
        ], [], ['practicum_id' => 'praktikum', 'pair_id' => 'pasangan Asdos']);

        $practicum = Practicum::query()->findOrFail($data['practicum_id']);
        $pair = AsdosPair::query()->findOrFail($data['pair_id']);

        try {
            $this->bookings->book($practicum, $pair, $request->user(), byAdmin: true);
        } catch (BookingException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Pasangan Asdos berhasil ditempatkan pada praktikum.');
    }

    /** Ganti pasangan pada booking aktif. */
    public function update(Request $request, Booking $booking): RedirectResponse
    {
        $data = $request->validate([
            'pair_id' => ['required', 'exists:asdos_pairs,id'],
        ], [], ['pair_id' => 'pasangan pengganti']);

        try {
            $this->bookings->reassign($booking, AsdosPair::query()->findOrFail($data['pair_id']), $request->user());
        } catch (BookingException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Pasangan Asdos berhasil diganti.');
    }

    /** Membatalkan booking — hanya Admin. */
    public function destroy(Request $request, Booking $booking): RedirectResponse
    {
        $note = $request->validate([
            'note' => ['nullable', 'string', 'max:255'],
        ])['note'] ?? null;

        try {
            $this->bookings->cancel($booking, $request->user(), $note);
        } catch (BookingException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Booking berhasil dibatalkan.');
    }

    /** Praktikum yang masih punya slot kosong, untuk dropdown penempatan manual. */
    private function bookablePracticums(): Collection
    {
        return Practicum::query()
            ->with(['course:id,name,semester', 'classRoom:id,class_name,angkatan,semester'])
            ->withCount(['bookings as active_bookings_count' => fn ($q) => $q->where('status', Booking::STATUS_AKTIF)])
            ->get()
            ->reject->isFilled()
            ->sortBy(fn (Practicum $p) => [
                -(int) ($p->classRoom?->angkatan ?? 0),
                $p->classRoom?->class_name ?? '',
                $p->course?->name ?? '',
            ])
            ->values()
            ->map(fn (Practicum $p) => [
                'id' => $p->id,
                'course_id' => $p->course_id,
                'label' => sprintf(
                    '%s — %s',
                    $p->course?->name ?? '-',
                    $p->classRoom?->label() ?? '-',
                ),
            ]);
    }

    /**
     * Peta course_id => pasangan yang boleh mengisinya, lengkap dengan sisa
     * kuota, agar dropdown Admin hanya menampilkan pilihan yang sah.
     *
     * @return array<int, array<int, array<string, mixed>>>
     */
    private function pairsByCourse(): array
    {
        $map = [];

        $pairs = AsdosPair::query()
            ->with(['asdosOne.user:id,name', 'asdosTwo.user:id,name'])
            ->get();

        foreach ($pairs as $pair) {
            $map[$pair->course_id][] = [
                'id' => $pair->id,
                'label' => $pair->label(),
                'remaining' => $pair->remainingQuota(),
                'availability' => $pair->availabilityStatus(),
            ];
        }

        foreach ($map as $courseId => $list) {
            usort($list, fn ($a, $b) => strcmp((string) $a['label'], (string) $b['label']));
            $map[$courseId] = $list;
        }

        return $map;
    }
}
