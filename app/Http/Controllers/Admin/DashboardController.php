<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Asdos;
use App\Models\AsdosPair;
use App\Models\Booking;
use App\Models\BookingLog;
use App\Models\ClassRoom;
use App\Models\Course;
use App\Models\Practicum;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /** Dashboard Koordinator: statistik ringkas + praktikum yang belum terisi. */
    public function __invoke(): Response
    {
        $practicums = Practicum::query()
            ->withCount(['bookings as active_bookings_count' => fn ($q) => $q->where('status', Booking::STATUS_AKTIF)])
            ->get();

        $filled = $practicums->filter->isFilled();

        $asdos = Asdos::query()->withQuotaUsage()->get();

        $aktif = $asdos->where('status', 'aktif');
        $penuh = $aktif->filter->isFull();

        return Inertia::render('Admin/Dashboard', [
            'stats' => [
                'total_courses' => Course::query()->count(),
                'total_classes' => ClassRoom::query()->count(),
                'total_practicums' => $practicums->count(),
                'total_asdos' => $asdos->count(),
                'total_pairs' => AsdosPair::query()->count(),
                'active_bookings' => Booking::query()->aktif()->count(),
                'practicums_filled' => $filled->count(),
                'practicums_empty' => $practicums->count() - $filled->count(),
                'asdos_full' => $penuh->count(),
                'asdos_available' => $aktif->count() - $penuh->count(),
            ],
            'emptyPracticums' => Practicum::query()
                ->with(['course:id,name,semester', 'classRoom.representative:id,name'])
                ->withCount(['bookings as active_bookings_count' => fn ($q) => $q->where('status', Booking::STATUS_AKTIF)])
                ->get()
                ->reject->isFilled()
                ->sortBy(fn (Practicum $p) => [
                    -(int) ($p->classRoom?->angkatan ?? 0),
                    $p->classRoom?->class_name ?? '',
                    $p->course?->name ?? '',
                ])
                ->take(12)
                ->values()
                ->map(fn (Practicum $p) => [
                    'id' => $p->id,
                    'course' => $p->course?->name,
                    'semester' => $p->course?->semester,
                    'class_label' => $p->classRoom?->label(),
                    'representative' => $p->classRoom?->representative?->name,
                ]),
            'recentLogs' => BookingLog::query()
                ->with('user:id,name')
                ->latest()
                ->take(8)
                ->get()
                ->map(fn (BookingLog $log) => [
                    'id' => $log->id,
                    'action' => $log->action,
                    'description' => $log->description,
                    'user' => $log->user?->name ?? 'Sistem',
                    'at' => $log->created_at?->translatedFormat('d M Y, H:i'),
                ]),
        ]);
    }
}
