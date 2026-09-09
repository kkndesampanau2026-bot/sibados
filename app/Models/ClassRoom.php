<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

/**
 * Rombel mahasiswa, mis. "Kelas A angkatan 2024 (semester V)".
 * Satu rombel punya satu ketua kelas dan mengampu beberapa praktikum.
 */
#[Fillable(['class_name', 'angkatan', 'semester', 'representative_id'])]
class ClassRoom extends Model
{
    protected $table = 'classes';

    protected function casts(): array
    {
        return [
            'angkatan' => 'integer',
        ];
    }

    /** Ketua kelas yang berhak melakukan booking untuk rombel ini. */
    public function representative(): BelongsTo
    {
        return $this->belongsTo(User::class, 'representative_id');
    }

    public function practicums(): HasMany
    {
        return $this->hasMany(Practicum::class, 'class_id');
    }

    public function bookings(): HasManyThrough
    {
        return $this->hasManyThrough(Booking::class, Practicum::class, 'class_id', 'practicum_id');
    }

    /** Jumlah praktikum yang sudah mendapatkan Asdos. */
    public function filledPracticums(): int
    {
        return $this->practicums->filter->isFilled()->count();
    }

    public function label(): string
    {
        return sprintf('Kelas %s Angkatan %s', $this->class_name, $this->angkatan);
    }
}
