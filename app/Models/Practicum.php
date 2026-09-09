<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Satu praktikum = satu rombel mengampu satu mata kuliah.
 * Inilah unit yang dibooking oleh ketua kelas.
 */
#[Fillable(['class_id', 'course_id', 'max_asdos', 'is_locked'])]
class Practicum extends Model
{
    protected function casts(): array
    {
        return [
            'is_locked' => 'boolean',
            'max_asdos' => 'integer',
        ];
    }

    public function classRoom(): BelongsTo
    {
        return $this->belongsTo(ClassRoom::class, 'class_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function activeBookings(): HasMany
    {
        return $this->bookings()->where('status', Booking::STATUS_AKTIF);
    }

    public function filledSlots(): int
    {
        return (int) ($this->active_bookings_count ?? $this->activeBookings()->count());
    }

    /** Kuota Asdos praktikum ini sudah terpenuhi. */
    public function isFilled(): bool
    {
        return $this->filledSlots() >= $this->max_asdos;
    }

    public function label(): string
    {
        return sprintf(
            '%s — %s',
            $this->course?->name ?? 'Mata kuliah',
            $this->classRoom?->label() ?? 'kelas',
        );
    }
}
