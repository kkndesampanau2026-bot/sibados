<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'practicum_id', 'pair_id', 'representative_id', 'status',
    'booked_at', 'cancelled_at', 'cancelled_by', 'note', 'active_flag',
])]
class Booking extends Model
{
    public const STATUS_AKTIF = 'aktif';

    public const STATUS_DIBATALKAN = 'dibatalkan';

    protected function casts(): array
    {
        return [
            'booked_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function practicum(): BelongsTo
    {
        return $this->belongsTo(Practicum::class);
    }

    public function pair(): BelongsTo
    {
        return $this->belongsTo(AsdosPair::class, 'pair_id');
    }

    public function representative(): BelongsTo
    {
        return $this->belongsTo(User::class, 'representative_id');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(BookingLog::class);
    }

    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_AKTIF);
    }
}
