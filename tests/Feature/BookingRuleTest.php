<?php

namespace Tests\Feature;

use App\Exceptions\BookingException;
use App\Models\Asdos;
use App\Models\AsdosPair;
use App\Models\Booking;
use App\Models\ClassRoom;
use App\Models\Course;
use App\Models\Practicum;
use App\Models\User;
use App\Services\BookingService;
use App\Services\BookingSettings;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Menguji aturan booking PASANGAN Asdos per praktikum. */
class BookingRuleTest extends TestCase
{
    use RefreshDatabase;

    private BookingService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(BookingService::class);

        // Booking dibuka tanpa batas jadwal.
        app(BookingSettings::class)->set(['booking_open' => '1', 'booking_start' => '', 'booking_end' => '']);
    }

    private function makeCourse(string $name = 'Pemrograman Mobile', string $semester = 'V'): Course
    {
        return Course::query()->create(['name' => $name, 'semester' => $semester, 'status' => 'aktif']);
    }

    private function makeClass(string $name = 'A', int $angkatan = 2024, string $semester = 'V'): ClassRoom
    {
        return ClassRoom::query()->create([
            'class_name' => $name,
            'angkatan' => $angkatan,
            'semester' => $semester,
            'representative_id' => $this->makeLeader("ketua-{$angkatan}-{$name}")->id,
        ]);
    }

    private function makeLeader(string $slug): User
    {
        return User::query()->create([
            'name' => 'Ketua '.$slug,
            'email' => $slug.'@test.local',
            'password' => 'password',
            'role' => User::ROLE_PERWAKILAN,
        ]);
    }

    private function makePracticum(ClassRoom $class, Course $course, int $maxAsdos = 1): Practicum
    {
        return $class->practicums()->create(['course_id' => $course->id, 'max_asdos' => $maxAsdos]);
    }

    /**
     * @param  array<int, Course>  $courses  mata kuliah yang boleh ditangani
     * @param  int  $maxClasses  kuota global (batas atas lintas mata kuliah)
     * @param  int  $courseQuota  kuota per mata kuliah
     */
    private function makeAsdos(string $name, array $courses = [], int $maxClasses = 3, int $courseQuota = 2): Asdos
    {
        $user = User::query()->create([
            'name' => $name,
            'email' => strtolower($name).'@asdos.test',
            'password' => 'password',
            'role' => User::ROLE_ASDOS,
        ]);

        $asdos = Asdos::query()->create([
            'user_id' => $user->id,
            'nim' => (string) random_int(10000000, 99999999),
            'max_classes' => $maxClasses,
            'status' => 'aktif',
        ]);

        $asdos->courses()->sync(
            collect($courses)->mapWithKeys(fn (Course $c) => [
                $c->id => ['max_practicums' => $courseQuota],
            ])->all(),
        );

        return $asdos;
    }

    private function makePair(Course $course, Asdos $one, Asdos $two): AsdosPair
    {
        return AsdosPair::query()->create([
            'course_id' => $course->id,
            'status' => 'aktif',
            ...AsdosPair::normalizeMembers($one->id, $two->id),
        ]);
    }

    private function admin(string $email = 'admin@test.local'): User
    {
        return User::query()->create([
            'name' => 'Koordinator',
            'email' => $email,
            'password' => 'password',
            'role' => User::ROLE_ADMIN,
        ]);
    }

    public function test_booking_menempatkan_pasangan_ke_praktikum(): void
    {
        $course = $this->makeCourse();
        $class = $this->makeClass();
        $practicum = $this->makePracticum($class, $course);
        $pair = $this->makePair($course, $this->makeAsdos('Andi', [$course]), $this->makeAsdos('Budi', [$course]));

        $booking = $this->service->book($practicum, $pair, $class->representative);

        $this->assertSame(Booking::STATUS_AKTIF, $booking->status);
        $this->assertSame(1, $pair->activeBookings()->count());
        $this->assertTrue($practicum->fresh()->isFilled());
    }

    /** Satu booking memakai kuota KEDUA anggota pasangan. */
    public function test_booking_memakai_kuota_kedua_anggota(): void
    {
        $course = $this->makeCourse();
        $class = $this->makeClass();
        $andi = $this->makeAsdos('Andi', [$course]);
        $budi = $this->makeAsdos('Budi', [$course]);
        $pair = $this->makePair($course, $andi, $budi);

        $this->service->book($this->makePracticum($class, $course), $pair, $class->representative);

        $this->assertSame(1, $andi->fresh()->usedQuota());
        $this->assertSame(1, $budi->fresh()->usedQuota());
    }

    /** Rombel memilih satu pasangan untuk tiap praktikum yang diampunya. */
    public function test_satu_kelas_memilih_pasangan_untuk_tiap_praktikumnya(): void
    {
        $class = $this->makeClass('A', 2024, 'V');

        $mobile = $this->makeCourse('Pemrograman Mobile');
        $bisnis = $this->makeCourse('Kecerdasan Bisnis');
        $audit = $this->makeCourse('Audit Sistem Informasi');

        $fajar = $this->makeAsdos('Fajar', [$mobile, $bisnis]);
        $rina = $this->makeAsdos('Rina', [$mobile, $bisnis]);
        $sinta = $this->makeAsdos('Sinta', [$audit]);
        $tono = $this->makeAsdos('Tono', [$audit]);

        $bookings = [
            [$this->makePracticum($class, $mobile), $this->makePair($mobile, $fajar, $rina)],
            [$this->makePracticum($class, $bisnis), $this->makePair($bisnis, $fajar, $rina)],
            [$this->makePracticum($class, $audit), $this->makePair($audit, $sinta, $tono)],
        ];

        foreach ($bookings as [$practicum, $pair]) {
            $this->service->book($practicum, $pair, $class->representative);
        }

        $class->load('practicums');
        $this->assertSame(3, $class->filledPracticums());
        // Fajar & Rina mengampu dua praktikum lewat dua pasangan berbeda.
        $this->assertSame(2, $fajar->fresh()->usedQuota());
        $this->assertSame(2, $rina->fresh()->usedQuota());
    }

    /** Siapa cepat dia dapat: slot yang sudah terisi tidak bisa direbut. */
    public function test_praktikum_yang_sudah_terisi_tidak_dapat_dibooking_ulang(): void
    {
        $course = $this->makeCourse();
        $class = $this->makeClass();
        $practicum = $this->makePracticum($class, $course);

        $pairA = $this->makePair($course, $this->makeAsdos('Andi', [$course]), $this->makeAsdos('Budi', [$course]));
        $pairB = $this->makePair($course, $this->makeAsdos('Citra', [$course]), $this->makeAsdos('Dimas', [$course]));

        $this->service->book($practicum, $pairA, $class->representative);

        $this->expectException(BookingException::class);
        $this->expectExceptionMessage('Praktikum ini sudah memiliki Asdos');

        $this->service->book($practicum, $pairB, $class->representative);
    }

    /** Pasangan hanya berlaku untuk mata kuliah tempat ia dibentuk. */
    public function test_pasangan_tidak_dapat_dipakai_di_mata_kuliah_lain(): void
    {
        $mobile = $this->makeCourse('Pemrograman Mobile');
        $audit = $this->makeCourse('Audit Sistem Informasi');
        $class = $this->makeClass();

        $pairMobile = $this->makePair(
            $mobile,
            $this->makeAsdos('Fajar', [$mobile, $audit]),
            $this->makeAsdos('Rina', [$mobile, $audit]),
        );

        $practicumAudit = $this->makePracticum($class, $audit);

        $this->expectException(BookingException::class);
        $this->expectExceptionMessage('tidak dibentuk untuk mata kuliah ini');

        $this->service->book($practicumAudit, $pairMobile, $class->representative);
    }

    /** Kuota individu: pasangan penuh bila SALAH SATU anggotanya sudah penuh. */
    public function test_pasangan_penuh_bila_salah_satu_anggota_penuh(): void
    {
        $course = $this->makeCourse();
        $andi = $this->makeAsdos('Andi', [$course], maxClasses: 1);
        $budi = $this->makeAsdos('Budi', [$course], maxClasses: 5);
        $pair = $this->makePair($course, $andi, $budi);

        $kelasA = $this->makeClass('A');
        $this->service->book($this->makePracticum($kelasA, $course), $pair, $kelasA->representative);

        $this->assertTrue($pair->fresh()->isFull());
        $this->assertSame(Asdos::STATUS_PENUH, $pair->fresh()->availabilityStatus());

        $kelasB = $this->makeClass('B');
        $practicum = $this->makePracticum($kelasB, $course);

        $this->expectException(BookingException::class);
        $this->expectExceptionMessage('Kuota total Asdos Andi sudah penuh');

        $this->service->book($practicum, $pair, $kelasB->representative);
    }

    /** Kuota terpakai lewat pasangan lain juga ikut diperhitungkan. */
    public function test_kuota_terpakai_lintas_pasangan(): void
    {
        $mobile = $this->makeCourse('Pemrograman Mobile');
        $bisnis = $this->makeCourse('Kecerdasan Bisnis');

        $fajar = $this->makeAsdos('Fajar', [$mobile, $bisnis], maxClasses: 1);
        $rina = $this->makeAsdos('Rina', [$mobile], maxClasses: 5);
        $tono = $this->makeAsdos('Tono', [$bisnis], maxClasses: 5);

        $kelasA = $this->makeClass('A');
        $this->service->book(
            $this->makePracticum($kelasA, $mobile),
            $this->makePair($mobile, $fajar, $rina),
            $kelasA->representative,
        );

        $this->assertSame(1, $fajar->fresh()->usedQuota());

        // Fajar sudah penuh, sehingga pasangannya di mata kuliah lain ikut penuh.
        $kelasB = $this->makeClass('B');

        $this->expectException(BookingException::class);
        $this->expectExceptionMessage('Kuota total Asdos Fajar sudah penuh');

        $this->service->book(
            $this->makePracticum($kelasB, $bisnis),
            $this->makePair($bisnis, $fajar, $tono),
            $kelasB->representative,
        );
    }

    public function test_status_hampir_penuh_saat_sisa_satu_kuota(): void
    {
        $course = $this->makeCourse();
        // Kuota matkul 3 agar yang mengikat adalah sisa kuota, bukan batas matkul.
        $andi = $this->makeAsdos('Andi', [$course], maxClasses: 3, courseQuota: 3);
        $budi = $this->makeAsdos('Budi', [$course], maxClasses: 3, courseQuota: 3);
        $pair = $this->makePair($course, $andi, $budi);

        foreach (['A', 'B'] as $name) {
            $class = $this->makeClass($name);
            $this->service->book($this->makePracticum($class, $course), $pair, $class->representative);
        }

        $this->assertSame(Asdos::STATUS_HAMPIR_PENUH, $pair->fresh()->availabilityStatus());
    }

    /** Praktikum multi-pasangan boleh menampung lebih dari satu pasangan. */
    public function test_praktikum_multi_pasangan(): void
    {
        $course = $this->makeCourse();
        $class = $this->makeClass();
        $practicum = $this->makePracticum($class, $course, maxAsdos: 2);

        $pairA = $this->makePair($course, $this->makeAsdos('Andi', [$course]), $this->makeAsdos('Budi', [$course]));
        $pairB = $this->makePair($course, $this->makeAsdos('Citra', [$course]), $this->makeAsdos('Dimas', [$course]));

        $this->service->book($practicum, $pairA, $class->representative);
        $this->service->book($practicum, $pairB, $class->representative);

        $this->assertSame(2, $practicum->fresh()->activeBookings()->count());
    }

    /** Seorang Asdos tidak boleh masuk dua kali ke praktikum yang sama. */
    public function test_anggota_tidak_boleh_masuk_dua_kali_lewat_pasangan_berbeda(): void
    {
        $course = $this->makeCourse();
        $class = $this->makeClass();
        $practicum = $this->makePracticum($class, $course, maxAsdos: 3);

        $fajar = $this->makeAsdos('Fajar', [$course]);
        $rina = $this->makeAsdos('Rina', [$course]);
        $sinta = $this->makeAsdos('Sinta', [$course]);

        $this->service->book($practicum, $this->makePair($course, $fajar, $rina), $class->representative);

        $this->expectException(BookingException::class);
        $this->expectExceptionMessage('Salah satu anggota pasangan sudah mengampu praktikum ini');

        // Fajar sudah masuk lewat pasangan sebelumnya.
        $this->service->book($practicum, $this->makePair($course, $fajar, $sinta), $class->representative);
    }

    public function test_pasangan_yang_sama_tidak_dapat_dibooking_dua_kali(): void
    {
        $course = $this->makeCourse();
        $class = $this->makeClass();
        $practicum = $this->makePracticum($class, $course, maxAsdos: 2);
        $pair = $this->makePair($course, $this->makeAsdos('Andi', [$course]), $this->makeAsdos('Budi', [$course]));

        $this->service->book($practicum, $pair, $class->representative);

        $this->expectException(BookingException::class);
        $this->expectExceptionMessage('sudah terdaftar pada praktikum ini');

        $this->service->book($practicum, $pair, $class->representative);
    }

    public function test_booking_ditolak_saat_periode_ditutup(): void
    {
        app(BookingSettings::class)->set(['booking_open' => '0']);

        $course = $this->makeCourse();
        $class = $this->makeClass();
        $pair = $this->makePair($course, $this->makeAsdos('Andi', [$course]), $this->makeAsdos('Budi', [$course]));

        $this->expectException(BookingException::class);
        $this->expectExceptionMessage('belum dibuka');

        $this->service->book($this->makePracticum($class, $course), $pair, $class->representative);
    }

    public function test_admin_tetap_dapat_menempatkan_saat_ditutup_dan_terkunci(): void
    {
        app(BookingSettings::class)->set(['booking_open' => '0']);

        $course = $this->makeCourse();
        $practicum = $this->makePracticum($this->makeClass(), $course);
        $practicum->update(['is_locked' => true]);
        $pair = $this->makePair($course, $this->makeAsdos('Andi', [$course]), $this->makeAsdos('Budi', [$course]));

        $booking = $this->service->book($practicum, $pair, $this->admin(), byAdmin: true);

        $this->assertSame(Booking::STATUS_AKTIF, $booking->status);
    }

    public function test_praktikum_terkunci_menolak_booking_ketua_kelas(): void
    {
        $course = $this->makeCourse();
        $class = $this->makeClass();
        $practicum = $this->makePracticum($class, $course);
        $practicum->update(['is_locked' => true]);
        $pair = $this->makePair($course, $this->makeAsdos('Andi', [$course]), $this->makeAsdos('Budi', [$course]));

        $this->expectException(BookingException::class);
        $this->expectExceptionMessage('dikunci');

        $this->service->book($practicum, $pair, $class->representative);
    }

    public function test_pasangan_dengan_anggota_nonaktif_tidak_dapat_dibooking(): void
    {
        $course = $this->makeCourse();
        $class = $this->makeClass();
        $andi = $this->makeAsdos('Andi', [$course]);
        $budi = $this->makeAsdos('Budi', [$course]);
        $pair = $this->makePair($course, $andi, $budi);

        $budi->update(['status' => 'nonaktif']);

        $this->expectException(BookingException::class);
        $this->expectExceptionMessage('Budi sedang tidak aktif');

        $this->service->book($this->makePracticum($class, $course), $pair->fresh(), $class->representative);
    }

    public function test_pasangan_nonaktif_tidak_dapat_dibooking(): void
    {
        $course = $this->makeCourse();
        $class = $this->makeClass();
        $pair = $this->makePair($course, $this->makeAsdos('Andi', [$course]), $this->makeAsdos('Budi', [$course]));
        $pair->update(['status' => 'nonaktif']);

        $this->expectException(BookingException::class);
        $this->expectExceptionMessage('Pasangan tersebut sedang tidak aktif');

        $this->service->book($this->makePracticum($class, $course), $pair->fresh(), $class->representative);
    }

    public function test_pembatalan_mengembalikan_kuota_kedua_anggota(): void
    {
        $course = $this->makeCourse();
        $class = $this->makeClass();
        $practicum = $this->makePracticum($class, $course);
        $andi = $this->makeAsdos('Andi', [$course]);
        $budi = $this->makeAsdos('Budi', [$course]);
        $pair = $this->makePair($course, $andi, $budi);

        $booking = $this->service->book($practicum, $pair, $class->representative);
        $this->service->cancel($booking, $this->admin(), 'Perubahan jadwal');

        $this->assertSame(0, $andi->fresh()->usedQuota());
        $this->assertSame(0, $budi->fresh()->usedQuota());
        $this->assertFalse($practicum->fresh()->isFilled());

        // Setelah dibatalkan, pasangan yang sama boleh dibooking ulang.
        $ulang = $this->service->book($practicum->fresh(), $pair, $class->representative);
        $this->assertSame(Booking::STATUS_AKTIF, $ulang->status);
    }

    public function test_ganti_pasangan_mencatat_perubahan(): void
    {
        $course = $this->makeCourse();
        $class = $this->makeClass();
        $practicum = $this->makePracticum($class, $course);

        $andi = $this->makeAsdos('Andi', [$course]);
        $budi = $this->makeAsdos('Budi', [$course]);
        $citra = $this->makeAsdos('Citra', [$course]);
        $dimas = $this->makeAsdos('Dimas', [$course]);

        $lama = $this->makePair($course, $andi, $budi);
        $baru = $this->makePair($course, $citra, $dimas);

        $booking = $this->service->book($practicum, $lama, $class->representative);
        $this->service->reassign($booking, $baru, $this->admin());

        $this->assertSame($baru->id, $booking->fresh()->pair_id);
        $this->assertSame(0, $andi->fresh()->usedQuota());
        $this->assertSame(1, $citra->fresh()->usedQuota());
        $this->assertDatabaseHas('booking_logs', ['action' => 'perubahan', 'new_value' => 'Citra & Dimas']);
    }

    /**
     * Proteksi race condition: unique index pada (practicum_id, pair_id, active_flag)
     * menolak baris aktif kedua walau validasi aplikasi dilewati.
     */
    public function test_unique_index_menolak_booking_aktif_ganda(): void
    {
        $course = $this->makeCourse();
        $practicum = $this->makePracticum($this->makeClass(), $course, maxAsdos: 5);
        $pair = $this->makePair($course, $this->makeAsdos('Andi', [$course]), $this->makeAsdos('Budi', [$course]));

        $row = [
            'practicum_id' => $practicum->id,
            'pair_id' => $pair->id,
            'status' => Booking::STATUS_AKTIF,
            'active_flag' => 1,
            'booked_at' => now(),
        ];

        Booking::query()->create($row);

        $this->expectException(QueryException::class);

        Booking::query()->create($row);
    }
    /*
    |--------------------------------------------------------------------------
    | Kuota per mata kuliah
    |--------------------------------------------------------------------------
    */

    /** Kuota matkul membatasi berapa kelas boleh memilih Asdos yang sama. */
    public function test_kuota_mata_kuliah_membatasi_jumlah_kelas(): void
    {
        $course = $this->makeCourse();

        // Kuota global longgar (5), tapi kuota mata kuliah hanya 2.
        $andi = $this->makeAsdos('Andi', [$course], maxClasses: 5, courseQuota: 2);
        $budi = $this->makeAsdos('Budi', [$course], maxClasses: 5, courseQuota: 2);
        $pair = $this->makePair($course, $andi, $budi);

        foreach (['A', 'B'] as $name) {
            $class = $this->makeClass($name);
            $this->service->book($this->makePracticum($class, $course), $pair, $class->representative);
        }

        $this->assertSame(2, $andi->fresh()->usedQuotaForCourse($course->id));
        $this->assertSame(0, $pair->fresh()->remainingQuota());
        $this->assertSame(Asdos::STATUS_PENUH, $pair->fresh()->availabilityStatus());

        // Kelas ketiga tidak bisa lagi memilih pasangan ini.
        $kelasC = $this->makeClass('C');

        $this->expectException(BookingException::class);
        $this->expectExceptionMessage('Kuota Asdos Andi untuk Pemrograman Mobile sudah penuh (2/2 praktikum)');

        $this->service->book($this->makePracticum($kelasC, $course), $pair, $kelasC->representative);
    }

    /** Kuota matkul yang habis di satu mata kuliah tidak memblokir yang lain. */
    public function test_kuota_matkul_terpisah_antar_mata_kuliah(): void
    {
        $mobile = $this->makeCourse('Pemrograman Mobile');
        $bisnis = $this->makeCourse('Kecerdasan Bisnis');

        $fajar = $this->makeAsdos('Fajar', [$mobile, $bisnis], maxClasses: 5, courseQuota: 1);
        $rina = $this->makeAsdos('Rina', [$mobile, $bisnis], maxClasses: 5, courseQuota: 1);

        $pairMobile = $this->makePair($mobile, $fajar, $rina);
        $pairBisnis = $this->makePair($bisnis, $fajar, $rina);

        $kelasA = $this->makeClass('A');
        $this->service->book($this->makePracticum($kelasA, $mobile), $pairMobile, $kelasA->representative);

        // Kuota Pemrograman Mobile habis...
        $this->assertSame(Asdos::STATUS_PENUH, $pairMobile->fresh()->availabilityStatus());
        // ...tapi Kecerdasan Bisnis masih tersedia.
        $this->assertSame(Asdos::STATUS_HAMPIR_PENUH, $pairBisnis->fresh()->availabilityStatus());

        $kelasB = $this->makeClass('B');
        $booking = $this->service->book(
            $this->makePracticum($kelasB, $bisnis),
            $pairBisnis,
            $kelasB->representative,
        );

        $this->assertSame(Booking::STATUS_AKTIF, $booking->status);
        $this->assertSame(2, $fajar->fresh()->usedQuota());
    }

    /** Kuota global tetap menjadi batas atas walau kuota matkul masih sisa. */
    public function test_kuota_global_tetap_membatasi(): void
    {
        $mobile = $this->makeCourse('Pemrograman Mobile');
        $bisnis = $this->makeCourse('Kecerdasan Bisnis');

        // Kuota matkul longgar (3 masing-masing) tapi kuota total hanya 2.
        $fajar = $this->makeAsdos('Fajar', [$mobile, $bisnis], maxClasses: 2, courseQuota: 3);
        $rina = $this->makeAsdos('Rina', [$mobile, $bisnis], maxClasses: 5, courseQuota: 3);

        $pairMobile = $this->makePair($mobile, $fajar, $rina);
        $pairBisnis = $this->makePair($bisnis, $fajar, $rina);

        $kelasA = $this->makeClass('A');
        $this->service->book($this->makePracticum($kelasA, $mobile), $pairMobile, $kelasA->representative);
        $this->service->book($this->makePracticum($kelasA, $bisnis), $pairBisnis, $kelasA->representative);

        $fajar = $fajar->fresh();
        $this->assertSame(2, $fajar->usedQuota());
        // Kuota matkul Pemrograman Mobile masih sisa, tapi kuota total Fajar habis.
        $this->assertSame(2, $fajar->remainingForCourse($mobile->id));
        $this->assertSame(0, $fajar->effectiveRemainingForCourse($mobile->id));

        $kelasB = $this->makeClass('B');

        $this->expectException(BookingException::class);
        $this->expectExceptionMessage('Kuota total Asdos Fajar sudah penuh (2/2 praktikum)');

        $this->service->book($this->makePracticum($kelasB, $mobile), $pairMobile, $kelasB->representative);
    }

    /** Kuota matkul dihitung lintas pasangan pada mata kuliah yang sama. */
    public function test_kuota_matkul_dihitung_lintas_pasangan(): void
    {
        $course = $this->makeCourse();

        $fajar = $this->makeAsdos('Fajar', [$course], maxClasses: 5, courseQuota: 1);
        $rina = $this->makeAsdos('Rina', [$course], maxClasses: 5, courseQuota: 5);
        $sinta = $this->makeAsdos('Sinta', [$course], maxClasses: 5, courseQuota: 5);

        $pairSatu = $this->makePair($course, $fajar, $rina);
        $pairDua = $this->makePair($course, $fajar, $sinta);

        $kelasA = $this->makeClass('A');
        $this->service->book($this->makePracticum($kelasA, $course), $pairSatu, $kelasA->representative);

        // Fajar habis di matkul ini, jadi pasangannya yang lain ikut penuh.
        $this->assertSame(Asdos::STATUS_PENUH, $pairDua->fresh()->availabilityStatus());

        $kelasB = $this->makeClass('B');

        $this->expectException(BookingException::class);
        $this->expectExceptionMessage('Kuota Asdos Fajar untuk Pemrograman Mobile sudah penuh (1/1 praktikum)');

        $this->service->book($this->makePracticum($kelasB, $course), $pairDua, $kelasB->representative);
    }

    /** Pembatalan mengembalikan kuota mata kuliah. */
    public function test_pembatalan_mengembalikan_kuota_mata_kuliah(): void
    {
        $course = $this->makeCourse();
        $andi = $this->makeAsdos('Andi', [$course], maxClasses: 5, courseQuota: 1);
        $budi = $this->makeAsdos('Budi', [$course], maxClasses: 5, courseQuota: 1);
        $pair = $this->makePair($course, $andi, $budi);

        $class = $this->makeClass();
        $booking = $this->service->book($this->makePracticum($class, $course), $pair, $class->representative);

        $this->assertSame(Asdos::STATUS_PENUH, $pair->fresh()->availabilityStatus());

        $this->service->cancel($booking, $this->admin());

        $this->assertSame(0, $andi->fresh()->usedQuotaForCourse($course->id));
        $this->assertSame(Asdos::STATUS_HAMPIR_PENUH, $pair->fresh()->availabilityStatus());
    }
}
