<?php

namespace App\Services;

use App\Exceptions\BookingException;
use App\Models\Asdos;
use App\Models\AsdosPair;
use App\Models\Booking;
use App\Models\BookingLog;
use App\Models\Practicum;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Pusat seluruh aturan booking Asdos.
 *
 * Yang dibooking adalah PASANGAN Asdos untuk sebuah PRAKTIKUM (rombel x mata
 * kuliah). Ketua kelas memilih satu pasangan per praktikum yang diampu
 * rombelnya, dengan prinsip siapa cepat dia dapat.
 *
 * Seluruh penulisan dibungkus transaksi database dengan penguncian baris
 * (SELECT ... FOR UPDATE) agar dua ketua kelas yang menekan tombol Booking
 * pada detik yang sama tidak dapat melewati pengecekan kuota bersamaan.
 */
class BookingService
{
    public function __construct(private readonly BookingSettings $settings) {}

    /**
     * Membuat booking baru: menempatkan sebuah pasangan pada satu praktikum.
     *
     * @param  bool  $byAdmin  Admin boleh menempatkan walau booking ditutup / praktikum terkunci.
     *
     * @throws BookingException
     */
    public function book(Practicum $practicum, AsdosPair $pair, User $actor, bool $byAdmin = false): Booking
    {
        if (! $byAdmin && ! $this->settings->isOpen()) {
            throw new BookingException($this->settings->closedReason() ?? 'Booking sedang ditutup.');
        }

        try {
            return DB::transaction(function () use ($practicum, $pair, $actor, $byAdmin) {
                // Kunci baris praktikum, pasangan, lalu kedua anggotanya dengan
                // urutan tetap (id menaik) untuk menghindari deadlock.
                $lockedPracticum = Practicum::query()->whereKey($practicum->getKey())->lockForUpdate()->firstOrFail();
                $lockedPair = AsdosPair::query()->whereKey($pair->getKey())->lockForUpdate()->firstOrFail();
                $this->lockMembers($lockedPair);

                $lockedPair->load('asdosOne.user', 'asdosTwo.user');

                $this->assertBookable($lockedPracticum, $lockedPair, $byAdmin);

                $lockedPracticum->loadMissing('course', 'classRoom');

                $booking = Booking::query()->create([
                    'practicum_id' => $lockedPracticum->id,
                    'pair_id' => $lockedPair->id,
                    'representative_id' => $byAdmin
                        ? $lockedPracticum->classRoom?->representative_id
                        : $actor->id,
                    'status' => Booking::STATUS_AKTIF,
                    'active_flag' => 1,
                    'booked_at' => now(),
                ]);

                $this->log(
                    $booking,
                    $actor,
                    $byAdmin ? 'penempatan_admin' : 'booking',
                    null,
                    $lockedPair->label(),
                    sprintf(
                        'Pasangan %s ditempatkan pada %s',
                        $lockedPair->label(),
                        $lockedPracticum->label(),
                    ),
                );

                return $booking;
            }, 3);
        } catch (QueryException $e) {
            // Jaring pengaman terakhir: unique index bookings_active_unique.
            if ($this->isDuplicateKey($e)) {
                throw new BookingException('Pasangan tersebut sudah terdaftar pada praktikum ini.');
            }

            throw $e;
        }
    }

    /**
     * Membatalkan booking aktif. Hanya Admin.
     *
     * @throws BookingException
     */
    public function cancel(Booking $booking, User $actor, ?string $note = null): Booking
    {
        return DB::transaction(function () use ($booking, $actor, $note) {
            $locked = Booking::query()->whereKey($booking->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== Booking::STATUS_AKTIF) {
                throw new BookingException('Booking ini sudah tidak aktif.');
            }

            $locked->update([
                'status' => Booking::STATUS_DIBATALKAN,
                'active_flag' => null,
                'cancelled_at' => now(),
                'cancelled_by' => $actor->id,
                'note' => $note,
            ]);

            $locked->loadMissing('pair.asdosOne.user', 'pair.asdosTwo.user', 'practicum.course', 'practicum.classRoom');

            $this->log($locked, $actor, 'pembatalan', $locked->pair?->label(), null, sprintf(
                'Booking pasangan %s pada %s dibatalkan',
                $locked->pair?->label() ?? 'Asdos',
                $locked->practicum?->label() ?? 'praktikum',
            ));

            return $locked;
        }, 3);
    }

    /**
     * Mengganti pasangan pada sebuah booking aktif (fitur Admin "Ganti Pasangan").
     *
     * @throws BookingException
     */
    public function reassign(Booking $booking, AsdosPair $newPair, User $actor): Booking
    {
        return DB::transaction(function () use ($booking, $newPair, $actor) {
            $locked = Booking::query()->whereKey($booking->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status !== Booking::STATUS_AKTIF) {
                throw new BookingException('Hanya booking aktif yang dapat diubah.');
            }

            if ((int) $locked->pair_id === (int) $newPair->getKey()) {
                throw new BookingException('Pasangan pengganti sama dengan pasangan saat ini.');
            }

            $practicum = Practicum::query()->whereKey($locked->practicum_id)->lockForUpdate()->firstOrFail();
            $target = AsdosPair::query()->whereKey($newPair->getKey())->lockForUpdate()->firstOrFail();
            $this->lockMembers($target);

            $target->load('asdosOne.user', 'asdosTwo.user');

            $this->assertPairUsable($target);
            $this->assertPairMatchesCourse($practicum, $target);
            $this->assertPairNotInPracticum($practicum, $target);
            $this->assertMembersFreeInPracticum($practicum, $target, $locked->getKey());
            $this->assertQuotaAvailable($target);

            $locked->loadMissing('pair.asdosOne.user', 'pair.asdosTwo.user');
            $oldLabel = $locked->pair?->label();

            $locked->update(['pair_id' => $target->id]);
            $practicum->loadMissing('course', 'classRoom');

            $this->log($locked, $actor, 'perubahan', $oldLabel, $target->label(), sprintf(
                'Pasangan %s diganti menjadi %s pada %s',
                $oldLabel ?? '-',
                $target->label(),
                $practicum->label(),
            ));

            return $locked;
        }, 3);
    }

    /**
     * Mengunci baris kedua anggota pasangan agar perhitungan kuota individu
     * tidak dapat dilewati dua transaksi bersamaan.
     */
    private function lockMembers(AsdosPair $pair): void
    {
        $ids = $pair->memberIds();
        sort($ids);

        foreach ($ids as $id) {
            Asdos::query()->whereKey($id)->lockForUpdate()->first();
        }
    }

    /**
     * Validasi berlapis sebelum booking diterima.
     *
     * @throws BookingException
     */
    private function assertBookable(Practicum $practicum, AsdosPair $pair, bool $byAdmin): void
    {
        if (! $byAdmin && $practicum->is_locked) {
            throw new BookingException('Praktikum ini telah dikunci oleh Koordinator.');
        }

        $this->assertPairUsable($pair);

        // Siapa cepat dia dapat: slot yang sudah terisi tidak bisa direbut lagi.
        if ($practicum->activeBookings()->count() >= $practicum->max_asdos) {
            throw new BookingException('Praktikum ini sudah memiliki Asdos. Hubungi Koordinator untuk perubahan.');
        }

        $this->assertPairMatchesCourse($practicum, $pair);
        $this->assertPairNotInPracticum($practicum, $pair);
        $this->assertMembersFreeInPracticum($practicum, $pair);
        $this->assertQuotaAvailable($pair);
    }

    private function assertPairUsable(AsdosPair $pair): void
    {
        if ($pair->status !== 'aktif') {
            throw new BookingException('Pasangan tersebut sedang tidak aktif.');
        }

        if ($pair->members()->count() < 2) {
            throw new BookingException('Pasangan tersebut belum lengkap.');
        }

        $nonaktif = $pair->members()->first(fn (Asdos $a) => $a->status !== 'aktif');

        if ($nonaktif) {
            throw new BookingException(sprintf(
                'Asdos %s sedang tidak aktif, sehingga pasangan ini tidak dapat dipilih.',
                $nonaktif->user?->name ?? '-',
            ));
        }
    }

    /** Pasangan hanya berlaku untuk mata kuliah tempat ia dibentuk. */
    private function assertPairMatchesCourse(Practicum $practicum, AsdosPair $pair): void
    {
        if ((int) $pair->course_id !== (int) $practicum->course_id) {
            throw new BookingException('Pasangan tersebut tidak dibentuk untuk mata kuliah ini.');
        }
    }

    private function assertPairNotInPracticum(Practicum $practicum, AsdosPair $pair): void
    {
        $exists = Booking::query()
            ->where('practicum_id', $practicum->getKey())
            ->where('pair_id', $pair->getKey())
            ->where('status', Booking::STATUS_AKTIF)
            ->exists();

        if ($exists) {
            throw new BookingException('Pasangan tersebut sudah terdaftar pada praktikum ini.');
        }
    }

    /**
     * Pada praktikum multi-pasangan, seorang Asdos tidak boleh masuk dua kali
     * lewat pasangan berbeda.
     */
    private function assertMembersFreeInPracticum(Practicum $practicum, AsdosPair $pair, ?int $ignoreBookingId = null): void
    {
        $memberIds = $pair->memberIds();

        $conflict = Booking::query()
            ->where('bookings.practicum_id', $practicum->getKey())
            ->where('bookings.status', Booking::STATUS_AKTIF)
            ->when($ignoreBookingId, fn ($q) => $q->whereKeyNot($ignoreBookingId))
            ->join('asdos_pairs', 'asdos_pairs.id', '=', 'bookings.pair_id')
            ->where(fn ($q) => $q
                ->whereIn('asdos_pairs.asdos_one_id', $memberIds)
                ->orWhereIn('asdos_pairs.asdos_two_id', $memberIds))
            ->exists();

        if ($conflict) {
            throw new BookingException('Salah satu anggota pasangan sudah mengampu praktikum ini.');
        }
    }

    /**
     * Kuota dihitung per individu dalam DUA lapis, keduanya harus masih ada sisa:
     *
     *  1. Kuota per mata kuliah (course_asdos.max_practicums) — membatasi berapa
     *     kelas boleh memilih Asdos ini untuk mata kuliah tersebut.
     *  2. Kuota global (asdos.max_classes) — batas atas beban lintas mata kuliah.
     */
    private function assertQuotaAvailable(AsdosPair $pair): void
    {
        $pair->loadMissing('course');
        $courseId = (int) $pair->course_id;
        $courseName = $pair->course?->name ?? 'mata kuliah ini';

        foreach ($pair->members() as $asdos) {
            $name = $asdos->user?->name ?? '-';

            // Lapis 1 — kuota mata kuliah.
            $courseMax = $asdos->maxForCourse($courseId);

            if ($courseMax === null) {
                throw new BookingException(sprintf(
                    'Asdos %s tidak ditugaskan pada %s.',
                    $name,
                    $courseName,
                ));
            }

            $courseUsed = $this->countActiveBookings($asdos, $courseId);

            if ($courseUsed >= $courseMax) {
                throw new BookingException(sprintf(
                    'Kuota Asdos %s untuk %s sudah penuh (%d/%d praktikum).',
                    $name,
                    $courseName,
                    $courseUsed,
                    $courseMax,
                ));
            }

            // Lapis 2 — kuota global.
            $totalUsed = $this->countActiveBookings($asdos);

            if ($totalUsed >= $asdos->max_classes) {
                throw new BookingException(sprintf(
                    'Kuota total Asdos %s sudah penuh (%d/%d praktikum).',
                    $name,
                    $totalUsed,
                    $asdos->max_classes,
                ));
            }
        }
    }

    /**
     * Menghitung booking aktif seorang Asdos lintas seluruh pasangannya,
     * opsional dibatasi pada satu mata kuliah.
     */
    private function countActiveBookings(Asdos $asdos, ?int $courseId = null): int
    {
        return Booking::query()
            ->join('asdos_pairs', 'asdos_pairs.id', '=', 'bookings.pair_id')
            ->where('bookings.status', Booking::STATUS_AKTIF)
            ->when($courseId, fn ($q) => $q->where('asdos_pairs.course_id', $courseId))
            ->where(fn ($q) => $q
                ->where('asdos_pairs.asdos_one_id', $asdos->getKey())
                ->orWhere('asdos_pairs.asdos_two_id', $asdos->getKey()))
            ->count();
    }

    public function log(
        ?Booking $booking,
        ?User $actor,
        string $action,
        ?string $oldValue = null,
        ?string $newValue = null,
        ?string $description = null,
    ): void {
        BookingLog::query()->create([
            'booking_id' => $booking?->getKey(),
            'user_id' => $actor?->getKey(),
            'action' => $action,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'description' => $description,
            'ip_address' => request()->ip(),
        ]);
    }

    private function isDuplicateKey(Throwable $e): bool
    {
        return str_contains(strtolower($e->getMessage()), 'duplicate entry')
            || str_contains($e->getMessage(), '1062');
    }
}
