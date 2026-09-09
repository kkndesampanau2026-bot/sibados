<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'code', 'semester', 'status'])]
class Course extends Model
{
    /** Praktikum mata kuliah ini pada tiap rombel. */
    public function practicums(): HasMany
    {
        return $this->hasMany(Practicum::class);
    }

    /** Pasangan Asdos yang dibentuk Koordinator untuk mata kuliah ini. */
    public function pairs(): HasMany
    {
        return $this->hasMany(AsdosPair::class);
    }

    /** Daftar Asdos yang diizinkan Admin menangani mata kuliah ini. */
    public function asdos(): BelongsToMany
    {
        return $this->belongsToMany(Asdos::class, 'course_asdos')->withTimestamps();
    }

    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('status', 'aktif');
    }
}
