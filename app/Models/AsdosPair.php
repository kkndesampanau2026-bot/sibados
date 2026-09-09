<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

/**
 * Pasangan Asisten Dosen untuk satu mata kuliah, dibentuk oleh Koordinator.
 * Inilah yang dipilih ketua kelas saat booking — bukan Asdos perorangan.
 */
#[Fillable(['course_id', 'asdos_one_id', 'asdos_two_id', 'status'])]
class AsdosPair extends Model
{
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function asdosOne(): BelongsTo
    {
        return $this->belongsTo(Asdos::class, 'asdos_one_id');
    }

    public function asdosTwo(): BelongsTo
    {
        return $this->belongsTo(Asdos::class, 'asdos_two_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'pair_id');
    }

    public function activeBookings(): HasMany
    {
        return $this->bookings()->where('status', Booking::STATUS_AKTIF);
    }

    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('status', 'aktif');
    }

    /**
     * Kedua anggota pasangan.
     *
     * @return Collection<int, Asdos>
     */
    public function members(): Collection
    {
        return collect([$this->asdosOne, $this->asdosTwo])->filter()->values();
    }

    /** @return array<int, int> */
    public function memberIds(): array
    {
        return array_values(array_filter([$this->asdos_one_id, $this->asdos_two_id]));
    }

    public function label(): string
    {
        return $this->members()->map(fn (Asdos $a) => $a->user?->name ?? '?')->implode(' & ');
    }

    /**
     * Sisa kuota pasangan pada mata kuliahnya = sisa terkecil di antara kedua
     * anggota, karena satu booking memakai kuota kedua-duanya. Untuk tiap
     * anggota dipakai nilai terkecil antara kuota mata kuliah dan kuota global.
     */
    public function remainingQuota(): int
    {
        $members = $this->members();

        if ($members->isEmpty()) {
            return 0;
        }

        return (int) $members
            ->map(fn (Asdos $a) => $a->effectiveRemainingForCourse($this->course_id))
            ->min();
    }

    public function isFull(): bool
    {
        return $this->remainingQuota() <= 0;
    }

    /**
     * Status ketersediaan pasangan. Pasangan nonaktif atau yang salah satu
     * anggotanya nonaktif tidak dapat dipilih.
     */
    public function availabilityStatus(): string
    {
        $members = $this->members();

        if ($this->status !== 'aktif' || $members->count() < 2) {
            return Asdos::STATUS_NONAKTIF;
        }

        if ($members->contains(fn (Asdos $a) => $a->status !== 'aktif')) {
            return Asdos::STATUS_NONAKTIF;
        }

        $remaining = $this->remainingQuota();

        return match (true) {
            $remaining <= 0 => Asdos::STATUS_PENUH,
            $remaining === 1 => Asdos::STATUS_HAMPIR_PENUH,
            default => Asdos::STATUS_TERSEDIA,
        };
    }

    /**
     * Ringkasan kuota tiap anggota pada mata kuliah pasangan ini, untuk
     * ditampilkan kepada ketua kelas.
     *
     * @return array<int, array<string, mixed>>
     */
    public function memberQuotaSummary(): array
    {
        return $this->members()->map(fn (Asdos $a) => [
            'id' => $a->id,
            'name' => $a->user?->name,
            'nim' => $a->nim,
            // Kontak ditampilkan agar ketua kelas dapat menghubungi Asdos.
            'email' => $a->user?->email,
            'phone' => $a->user?->phone,
            'semester' => $a->semester,
            'course_used' => $a->usedQuotaForCourse($this->course_id),
            'course_max' => $a->maxForCourse($this->course_id),
            'total_used' => $a->usedQuota(),
            'total_max' => $a->max_classes,
            'status' => $a->availabilityForCourse($this->course_id),
        ])->values()->all();
    }

    /**
     * Menyusun kolom anggota selalu terurut agar unique index bekerja tanpa
     * memedulikan urutan input Koordinator.
     *
     * @return array{asdos_one_id: int, asdos_two_id: int}
     */
    public static function normalizeMembers(int $first, int $second): array
    {
        return [
            'asdos_one_id' => min($first, $second),
            'asdos_two_id' => max($first, $second),
        ];
    }
}
