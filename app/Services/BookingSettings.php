<?php

namespace App\Services;

use App\Models\Setting;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * Pengaturan global periode booking (PRD pasal 24: countdown & lock booking).
 */
class BookingSettings
{
    private const CACHE_KEY = 'sibados.settings';

    public const DEFAULTS = [
        'booking_open' => '0',
        'booking_start' => '',
        'booking_end' => '',
        'default_max_classes' => '3',
        'default_max_asdos' => '1',
        'default_course_quota' => '2',
    ];

    /** @return array<string, string> */
    public function all(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            $stored = Setting::query()->pluck('value', 'key')->all();

            return array_merge(self::DEFAULTS, $stored);
        });
    }

    public function get(string $key, ?string $default = null): ?string
    {
        return $this->all()[$key] ?? $default ?? self::DEFAULTS[$key] ?? null;
    }

    /** @param array<string, string|null> $values */
    public function set(array $values): void
    {
        foreach ($values as $key => $value) {
            Setting::query()->updateOrCreate(['key' => $key], ['value' => (string) $value]);
        }

        Cache::forget(self::CACHE_KEY);
    }

    public function startsAt(): ?CarbonImmutable
    {
        $raw = $this->get('booking_start');

        return $raw ? CarbonImmutable::parse($raw) : null;
    }

    public function endsAt(): ?CarbonImmutable
    {
        $raw = $this->get('booking_end');

        return $raw ? CarbonImmutable::parse($raw) : null;
    }

    /**
     * Booking terbuka jika saklar Admin menyala DAN waktu sekarang berada
     * di dalam jadwal (jika jadwal diisi). Jadwal kosong = tanpa batas waktu.
     */
    public function isOpen(): bool
    {
        if ($this->get('booking_open') !== '1') {
            return false;
        }

        $now = CarbonImmutable::now();

        if (($start = $this->startsAt()) && $now->lt($start)) {
            return false;
        }

        if (($end = $this->endsAt()) && $now->gt($end)) {
            return false;
        }

        return true;
    }

    /** Alasan booking tertutup, untuk ditampilkan ke perwakilan kelas. */
    public function closedReason(): ?string
    {
        if ($this->isOpen()) {
            return null;
        }

        if ($this->get('booking_open') !== '1') {
            return 'Booking Asdos belum dibuka oleh Koordinator.';
        }

        $now = CarbonImmutable::now();

        if (($start = $this->startsAt()) && $now->lt($start)) {
            return 'Booking baru dibuka pada '.$start->translatedFormat('d F Y, H:i').'.';
        }

        if (($end = $this->endsAt()) && $now->gt($end)) {
            return 'Periode booking telah ditutup pada '.$end->translatedFormat('d F Y, H:i').'.';
        }

        return 'Booking Asdos sedang tidak tersedia.';
    }

    /** @return array<string, mixed> Ringkasan untuk dikirim ke frontend. */
    public function summary(): array
    {
        return [
            'is_open' => $this->isOpen(),
            'closed_reason' => $this->closedReason(),
            'starts_at' => $this->startsAt()?->toIso8601String(),
            'ends_at' => $this->endsAt()?->toIso8601String(),
        ];
    }
}
