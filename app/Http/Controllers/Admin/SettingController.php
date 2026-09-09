<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\BookingService;
use App\Services\BookingSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Pengaturan sistem: membuka/menutup booking dan jadwalnya (PRD pasal 24). */
class SettingController extends Controller
{
    public function __construct(
        private readonly BookingSettings $settings,
        private readonly BookingService $bookings,
    ) {}

    public function edit(): Response
    {
        return Inertia::render('Admin/Settings/Edit', [
            'settings' => [
                'booking_open' => $this->settings->get('booking_open') === '1',
                'booking_start' => $this->toInput($this->settings->get('booking_start')),
                'booking_end' => $this->toInput($this->settings->get('booking_end')),
                'default_max_classes' => (int) $this->settings->get('default_max_classes'),
                'default_max_asdos' => (int) $this->settings->get('default_max_asdos'),
                'default_course_quota' => (int) $this->settings->get('default_course_quota'),
            ],
            'status' => $this->settings->summary(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'booking_open' => ['required', 'boolean'],
            'booking_start' => ['nullable', 'date'],
            'booking_end' => ['nullable', 'date', 'after:booking_start'],
            'default_max_classes' => ['required', 'integer', 'min:1', 'max:20'],
            'default_max_asdos' => ['required', 'integer', 'min:1', 'max:10'],
            'default_course_quota' => ['required', 'integer', 'min:1', 'max:20'],
        ], [
            'booking_end.after' => 'Waktu tutup harus setelah waktu buka.',
        ], [
            'booking_open' => 'status booking',
            'booking_start' => 'waktu buka',
            'booking_end' => 'waktu tutup',
            'default_max_classes' => 'default maksimal kelas per Asdos',
            'default_max_asdos' => 'default kuota Asdos per kelas',
            'default_course_quota' => 'default kuota Asdos per mata kuliah',
        ]);

        $wasOpen = $this->settings->isOpen();

        $this->settings->set([
            'booking_open' => $data['booking_open'] ? '1' : '0',
            'booking_start' => $data['booking_start'] ?? '',
            'booking_end' => $data['booking_end'] ?? '',
            'default_max_classes' => (string) $data['default_max_classes'],
            'default_max_asdos' => (string) $data['default_max_asdos'],
            'default_course_quota' => (string) $data['default_course_quota'],
        ]);

        if ($wasOpen !== $this->settings->isOpen()) {
            $this->bookings->log(
                null,
                $request->user(),
                'pengaturan',
                $wasOpen ? 'terbuka' : 'tertutup',
                $this->settings->isOpen() ? 'terbuka' : 'tertutup',
                'Status periode booking diubah oleh Koordinator',
            );
        }

        return back()->with('success', 'Pengaturan berhasil disimpan.');
    }

    /** Format untuk <input type="datetime-local">. */
    private function toInput(?string $value): ?string
    {
        return filled($value) ? substr(str_replace(' ', 'T', $value), 0, 16) : null;
    }
}
