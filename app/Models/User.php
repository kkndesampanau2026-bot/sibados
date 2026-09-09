<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'phone', 'role', 'is_active', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_ADMIN = 'admin';

    public const ROLE_ASDOS = 'asdos';

    public const ROLE_PERWAKILAN = 'perwakilan';

    /**
     * Nilai bawaan agar instance baru selaras dengan default kolom di database.
     * Tanpa ini, user yang baru dibuat belum memuat is_active sehingga
     * middleware peran menganggapnya nonaktif.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    /** Profil Asdos milik user ini (hanya untuk role asdos). */
    public function asdos(): HasOne
    {
        return $this->hasOne(Asdos::class);
    }

    /** Rombel yang diketuai user ini (hanya untuk role perwakilan). */
    public function representedClasses(): HasMany
    {
        return $this->hasMany(ClassRoom::class, 'representative_id');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'representative_id');
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isAsdos(): bool
    {
        return $this->role === self::ROLE_ASDOS;
    }

    public function isPerwakilan(): bool
    {
        return $this->role === self::ROLE_PERWAKILAN;
    }
}
