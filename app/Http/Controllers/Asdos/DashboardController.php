<?php

namespace App\Http\Controllers\Asdos;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use Inertia\Inertia;
use Inertia\Response;

/** Dashboard Asdos: Profil Saya + Praktikum yang saya ampu. */
class DashboardController extends Controller
{
    public function __invoke(): Response
    {
        $asdos = auth()->user()->asdos;

        abort_unless($asdos, 403, 'Profil Asdos Anda belum dibuat. Hubungi Koordinator.');

        $asdos->load(['courses:id,name,semester']);

        // Pasangan tempat Asdos ini tergabung, beserta booking aktifnya.
        $pairs = $asdos->pairs()
            ->with(['course:id,name,semester', 'asdosOne.user:id,name', 'asdosTwo.user:id,name'])
            ->get();

        $bookings = $asdos->activeBookings()
            ->with([
                'pair.asdosOne.user:id,name',
                'pair.asdosTwo.user:id,name',
                'practicum.course:id,name,semester',
                'practicum.classRoom.representative:id,name,phone',
                'representative:id,name,phone',
            ])
            ->get();

        return Inertia::render('Asdos/Dashboard', [
            'profile' => [
                'name' => $asdos->user?->name,
                'nim' => $asdos->nim,
                'email' => $asdos->user?->email,
                'phone' => $asdos->user?->phone,
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
                ]),
            ],
            'pairs' => $pairs->map(fn ($pair) => [
                'id' => $pair->id,
                'course' => $pair->course?->name,
                'semester' => $pair->course?->semester,
                'label' => $pair->label(),
                'partner' => $pair->members()
                    ->first(fn ($m) => $m->id !== $asdos->id)?->user?->name,
                'status' => $pair->status,
            ])->values(),
            'practicums' => $bookings
                ->sortBy(fn (Booking $b) => [
                    $b->practicum?->course?->name,
                    $b->practicum?->classRoom?->class_name,
                ])
                ->values()
                ->map(fn (Booking $b) => [
                    'id' => $b->id,
                    'course' => $b->practicum?->course?->name,
                    'semester' => $b->practicum?->course?->semester,
                    'class_label' => $b->practicum?->classRoom?->label(),
                    'pair' => $b->pair?->label(),
                    'partner' => $b->pair?->members()
                        ->first(fn ($m) => $m->id !== $asdos->id)?->user?->name,
                    'representative' => $b->representative?->name
                        ?? $b->practicum?->classRoom?->representative?->name,
                    'representative_phone' => $b->representative?->phone
                        ?? $b->practicum?->classRoom?->representative?->phone,
                    'booked_at' => $b->booked_at?->translatedFormat('d M Y, H:i'),
                ]),
        ]);
    }
}
