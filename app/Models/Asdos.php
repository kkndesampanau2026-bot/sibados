<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

#[Fillable(['user_id', 'nim', 'semester', 'photo_path', 'max_classes', 'status'])]
class Asdos extends Model
{
    protected $table = 'asdos';

    /** Status ketersediaan yang ditampilkan pada halaman booking. */
    public const STATUS_TERSEDIA = 'tersedia';

    public const STATUS_HAMPIR_PENUH = 'hampir_penuh';

    public const STATUS_PENUH = 'penuh';

    public const STATUS_NONAKTIF = 'nonaktif';

    /** Cache hitungan per mata kuliah agar tidak query berulang dalam satu request. */
    private array $courseUsageCache = [];

    protected function casts(): array
    {
        return [
            'max_classes' => 'integer',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Mata kuliah yang boleh ditangani, lengkap dengan kuota per mata kuliah. */
    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'course_asdos')
            ->withPivot('max_practicums')
            ->withTimestamps();
    }

    public function pairsAsOne(): HasMany
    {
        return $this->hasMany(AsdosPair::class, 'asdos_one_id');
    }

    public function pairsAsTwo(): HasMany
    {
        return $this->hasMany(AsdosPair::class, 'asdos_two_id');
    }

    /** Seluruh pasangan yang memuat Asdos ini, lintas mata kuliah. */
    public function pairs(): Builder
    {
        return AsdosPair::query()->where(fn (Builder $q) => $q
            ->where('asdos_one_id', $this->getKey())
            ->orWhere('asdos_two_id', $this->getKey()));
    }

    /** Booking aktif dari seluruh pasangan yang memuat Asdos ini. */
    public function activeBookings(): Builder
    {
        return Booking::query()
            ->where('status', Booking::STATUS_AKTIF)
            ->whereIn('pair_id', $this->pairs()->select('asdos_pairs.id'));
    }

    public function scopeAktif(Builder $query): Builder
    {
        return $query->where('status', 'aktif');
    }

    /** Menyertakan jumlah booking aktif (lintas mata kuliah) tanpa query per baris. */
    public function scopeWithQuotaUsage(Builder $query): Builder
    {
        $count = Booking::query()
            ->selectRaw('count(*)')
            ->join('asdos_pairs', 'asdos_pairs.id', '=', 'bookings.pair_id')
            ->where('bookings.status', Booking::STATUS_AKTIF)
            ->where(fn ($q) => $q
                ->whereColumn('asdos_pairs.asdos_one_id', 'asdos.id')
                ->orWhereColumn('asdos_pairs.asdos_two_id', 'asdos.id'));

        return $query->select('asdos.*')->selectSub($count, 'active_bookings_count');
    }

    /*
    |--------------------------------------------------------------------------
    | Kuota global (batas atas lintas seluruh mata kuliah)
    |--------------------------------------------------------------------------
    */

    public function usedQuota(): int
    {
        return (int) ($this->active_bookings_count ?? $this->activeBookings()->count());
    }

    public function remainingQuota(): int
    {
        return max(0, $this->max_classes - $this->usedQuota());
    }

    public function isFull(): bool
    {
        return $this->usedQuota() >= $this->max_classes;
    }

    /*
    |--------------------------------------------------------------------------
    | Kuota per mata kuliah (pembatas utama pemilihan oleh kelas)
    |--------------------------------------------------------------------------
    */

    /** Berapa praktikum mata kuliah ini yang sedang diampu Asdos. */
    public function usedQuotaForCourse(int $courseId): int
    {
        if (! array_key_exists($courseId, $this->courseUsageCache)) {
            $this->courseUsageCache[$courseId] = Booking::query()
                ->join('asdos_pairs', 'asdos_pairs.id', '=', 'bookings.pair_id')
                ->where('bookings.status', Booking::STATUS_AKTIF)
                ->where('asdos_pairs.course_id', $courseId)
                ->where(fn ($q) => $q
                    ->where('asdos_pairs.asdos_one_id', $this->getKey())
                    ->orWhere('asdos_pairs.asdos_two_id', $this->getKey()))
                ->count();
        }

        return $this->courseUsageCache[$courseId];
    }

    /** Menyuntikkan hasil hitung dari luar (untuk menghindari N+1 pada daftar). */
    public function primeCourseUsage(int $courseId, int $used): void
    {
        $this->courseUsageCache[$courseId] = $used;
    }

    /** Kuota maksimal Asdos ini pada satu mata kuliah, null bila tidak ditugaskan. */
    public function maxForCourse(int $courseId): ?int
    {
        if ($this->relationLoaded('courses')) {
            $course = $this->courses->firstWhere('id', $courseId);

            return $course ? (int) $course->pivot->max_practicums : null;
        }

        $value = DB::table('course_asdos')
            ->where('asdos_id', $this->getKey())
            ->where('course_id', $courseId)
            ->value('max_practicums');

        return $value === null ? null : (int) $value;
    }

    /** Sisa kuota pada satu mata kuliah; 0 bila belum ditugaskan di sana. */
    public function remainingForCourse(int $courseId): int
    {
        $max = $this->maxForCourse($courseId);

        if ($max === null) {
            return 0;
        }

        return max(0, $max - $this->usedQuotaForCourse($courseId));
    }

    /**
     * Sisa yang benar-benar dapat dipakai pada satu mata kuliah: yang terkecil
     * antara sisa kuota mata kuliah dan sisa kuota global.
     */
    public function effectiveRemainingForCourse(int $courseId): int
    {
        return min($this->remainingForCourse($courseId), $this->remainingQuota());
    }

    /*
    |--------------------------------------------------------------------------
    | Status
    |--------------------------------------------------------------------------
    */

    /** Status keseluruhan Asdos (berdasarkan kuota global). */
    public function availabilityStatus(): string
    {
        if ($this->status !== 'aktif') {
            return self::STATUS_NONAKTIF;
        }

        return $this->statusFromRemaining($this->remainingQuota());
    }

    /** Status Asdos pada satu mata kuliah tertentu. */
    public function availabilityForCourse(int $courseId): string
    {
        if ($this->status !== 'aktif') {
            return self::STATUS_NONAKTIF;
        }

        return $this->statusFromRemaining($this->effectiveRemainingForCourse($courseId));
    }

    private function statusFromRemaining(int $remaining): string
    {
        return match (true) {
            $remaining <= 0 => self::STATUS_PENUH,
            $remaining === 1 => self::STATUS_HAMPIR_PENUH,
            default => self::STATUS_TERSEDIA,
        };
    }
}
