<?php

namespace Tests\Feature;

use App\Models\Asdos;
use App\Models\AsdosPair;
use App\Models\Booking;
use App\Models\ClassRoom;
use App\Models\Course;
use App\Models\Practicum;
use App\Models\User;
use App\Services\BookingSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/** Menguji hak akses per peran dan alur booking pasangan dari sisi HTTP. */
class AccessAndFlowTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::query()->create([
            'name' => 'Koordinator', 'email' => 'admin@flow.test',
            'password' => 'password', 'role' => User::ROLE_ADMIN,
        ]);
    }

    private function leader(string $email = 'ahmad@flow.test', string $name = 'Ahmad'): User
    {
        return User::query()->create([
            'name' => $name, 'email' => $email,
            'password' => 'password', 'role' => User::ROLE_PERWAKILAN,
        ]);
    }

    private function makeAsdos(string $name, array $courses): Asdos
    {
        $user = User::query()->create([
            'name' => $name, 'email' => strtolower($name).'@flow.test',
            'password' => 'password', 'role' => User::ROLE_ASDOS,
        ]);

        $asdos = Asdos::query()->create([
            'user_id' => $user->id,
            'nim' => (string) random_int(10000000, 99999999),
            'max_classes' => 3, 'status' => 'aktif',
        ]);

        $asdos->courses()->sync(collect($courses)->pluck('id'));

        return $asdos;
    }

    /**
     * Skenario dasar: Kelas A angkatan 2024 (semester V) dengan tiga praktikum,
     * dan pasangan Asdos untuk mata kuliah Pemrograman Mobile.
     *
     * @return array<string, mixed>
     */
    private function scenario(): array
    {
        $mobile = Course::query()->create(['name' => 'Pemrograman Mobile', 'semester' => 'V', 'status' => 'aktif']);
        $bisnis = Course::query()->create(['name' => 'Kecerdasan Bisnis', 'semester' => 'V', 'status' => 'aktif']);
        $audit = Course::query()->create(['name' => 'Audit Sistem Informasi', 'semester' => 'V', 'status' => 'aktif']);

        $leader = $this->leader();

        $class = ClassRoom::query()->create([
            'class_name' => 'A', 'angkatan' => 2024, 'semester' => 'V',
            'representative_id' => $leader->id,
        ]);

        $practicums = [];
        foreach ([$mobile, $bisnis, $audit] as $course) {
            $practicums[$course->name] = $class->practicums()->create([
                'course_id' => $course->id, 'max_asdos' => 1,
            ]);
        }

        $fajar = $this->makeAsdos('Fajar', [$mobile]);
        $rina = $this->makeAsdos('Rina', [$mobile]);

        $pair = AsdosPair::query()->create([
            'course_id' => $mobile->id,
            'status' => 'aktif',
            ...AsdosPair::normalizeMembers($fajar->id, $rina->id),
        ]);

        app(BookingSettings::class)->set(['booking_open' => '1', 'booking_start' => '', 'booking_end' => '']);

        return compact('class', 'leader', 'pair', 'fajar', 'rina', 'practicums', 'mobile');
    }

    public function test_tamu_diarahkan_ke_halaman_login(): void
    {
        $this->get('/admin/dashboard')->assertRedirect('/login');
        $this->get('/')->assertRedirect('/login');
    }

    public function test_ketua_kelas_tidak_dapat_membuka_dashboard_admin(): void
    {
        $this->actingAs($this->leader())->get('/admin/dashboard')->assertForbidden();
    }

    public function test_asdos_tidak_dapat_membuka_halaman_admin(): void
    {
        ['fajar' => $fajar] = $this->scenario();

        $this->actingAs($fajar->user)->get('/admin/pasangan')->assertForbidden();
    }

    public function test_akun_nonaktif_tidak_dapat_login(): void
    {
        $user = $this->leader('nonaktif@flow.test');
        $user->update(['is_active' => false]);

        $this->post('/login', ['email' => 'nonaktif@flow.test', 'password' => 'password'])
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_login_berhasil_mengarahkan_ke_dashboard_sesuai_peran(): void
    {
        $this->leader();

        $this->post('/login', ['email' => 'ahmad@flow.test', 'password' => 'password'])
            ->assertRedirect('/');

        $this->assertAuthenticated();
        $this->get('/')->assertRedirect('/perwakilan/dashboard');
    }

    public function test_ketua_kelas_melihat_semua_praktikum_kelasnya(): void
    {
        ['leader' => $leader] = $this->scenario();

        $this->actingAs($leader)
            ->get('/perwakilan/dashboard')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Perwakilan/Dashboard')
                ->where('classRoom.label', 'Kelas A Angkatan 2024')
                ->where('classRoom.total', 3)
                ->where('classRoom.filled', 0)
                ->has('practicums', 3));
    }

    /** Yang ditawarkan adalah pasangan, bukan Asdos perorangan. */
    public function test_ketua_kelas_melihat_pasangan_untuk_mata_kuliah_praktikum(): void
    {
        ['leader' => $leader, 'practicums' => $practicums] = $this->scenario();

        $this->actingAs($leader)
            ->get("/perwakilan/praktikum/{$practicums['Pemrograman Mobile']->id}/booking")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Perwakilan/Booking')
                ->where('practicum.course', 'Pemrograman Mobile')
                ->has('pairs', 1)
                ->where('pairs.0.label', 'Fajar & Rina')
                ->where('pairs.0.availability', 'tersedia')
                ->has('pairs.0.members', 2));

        // Belum ada pasangan untuk Kecerdasan Bisnis.
        $this->actingAs($leader)
            ->get("/perwakilan/praktikum/{$practicums['Kecerdasan Bisnis']->id}/booking")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->has('pairs', 0));
    }

    public function test_ketua_kelas_tidak_dapat_membooking_praktikum_kelas_lain(): void
    {
        ['practicums' => $practicums, 'pair' => $pair] = $this->scenario();

        $lain = $this->leader('lain@flow.test', 'Bayu');
        $target = $practicums['Pemrograman Mobile'];

        $this->actingAs($lain)->get("/perwakilan/praktikum/{$target->id}/booking")->assertForbidden();
        $this->actingAs($lain)
            ->post("/perwakilan/praktikum/{$target->id}/booking", ['pair_id' => $pair->id])
            ->assertForbidden();
    }

    /** Booking pasangan muncul di dashboard KEDUA anggotanya. */
    public function test_alur_booking_sampai_muncul_di_dashboard_kedua_asdos(): void
    {
        ['leader' => $leader, 'pair' => $pair, 'fajar' => $fajar, 'rina' => $rina, 'practicums' => $practicums] = $this->scenario();

        $target = $practicums['Pemrograman Mobile'];

        $this->actingAs($leader)
            ->post("/perwakilan/praktikum/{$target->id}/booking", ['pair_id' => $pair->id])
            ->assertRedirect('/perwakilan/dashboard')
            ->assertSessionHas('success');

        $this->assertDatabaseHas('bookings', [
            'practicum_id' => $target->id,
            'pair_id' => $pair->id,
            'representative_id' => $leader->id,
            'status' => 'aktif',
        ]);

        foreach ([[$fajar, 'Rina'], [$rina, 'Fajar']] as [$asdos, $partner]) {
            $this->actingAs($asdos->user)
                ->get('/asdos/dashboard')
                ->assertOk()
                ->assertInertia(fn (AssertableInertia $page) => $page
                    ->component('Asdos/Dashboard')
                    ->has('practicums', 1)
                    ->where('practicums.0.course', 'Pemrograman Mobile')
                    ->where('practicums.0.class_label', 'Kelas A Angkatan 2024')
                    ->where('practicums.0.pair', 'Fajar & Rina')
                    ->where('practicums.0.partner', $partner)
                    ->where('profile.used_quota', 1));
        }
    }

    public function test_dashboard_admin_menampilkan_statistik(): void
    {
        $this->scenario();

        $this->actingAs($this->admin())
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Admin/Dashboard')
                ->where('stats.total_courses', 3)
                ->where('stats.total_practicums', 3)
                ->where('stats.total_pairs', 1)
                ->where('stats.total_asdos', 2)
                ->where('stats.practicums_empty', 3));
    }

    public function test_admin_dapat_membatalkan_booking(): void
    {
        ['leader' => $leader, 'pair' => $pair, 'fajar' => $fajar, 'practicums' => $practicums] = $this->scenario();

        $target = $practicums['Pemrograman Mobile'];

        $this->actingAs($leader)->post("/perwakilan/praktikum/{$target->id}/booking", ['pair_id' => $pair->id]);
        $booking = Booking::query()->firstOrFail();

        $this->actingAs($this->admin())
            ->delete("/admin/booking/{$booking->id}", ['note' => 'Perubahan jadwal'])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'status' => 'dibatalkan']);
        $this->assertFalse($target->fresh()->isFilled());
        $this->assertSame(0, $fajar->fresh()->usedQuota());
    }

    public function test_booking_ditolak_saat_periode_ditutup(): void
    {
        ['leader' => $leader, 'pair' => $pair, 'practicums' => $practicums] = $this->scenario();

        app(BookingSettings::class)->set(['booking_open' => '0']);

        $this->actingAs($leader)
            ->post("/perwakilan/praktikum/{$practicums['Pemrograman Mobile']->id}/booking", ['pair_id' => $pair->id])
            ->assertSessionHas('error');

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_praktikum_terkunci_menolak_booking_ketua_kelas(): void
    {
        ['leader' => $leader, 'pair' => $pair, 'practicums' => $practicums] = $this->scenario();

        $target = $practicums['Pemrograman Mobile'];

        $this->actingAs($this->admin())->post("/admin/praktikum/{$target->id}/kunci");
        $this->assertTrue($target->fresh()->is_locked);

        $this->actingAs($leader)
            ->post("/perwakilan/praktikum/{$target->id}/booking", ['pair_id' => $pair->id])
            ->assertSessionHas('error');

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_membuat_kelas_otomatis_membuat_praktikum_semesternya(): void
    {
        $admin = $this->admin();

        foreach (['Pemrograman Mobile', 'Kecerdasan Bisnis', 'Audit Sistem Informasi'] as $name) {
            Course::query()->create(['name' => $name, 'semester' => 'V', 'status' => 'aktif']);
        }
        Course::query()->create(['name' => 'Logika Diskrit', 'semester' => 'I', 'status' => 'aktif']);

        $this->actingAs($admin)
            ->post('/admin/kelas', [
                'class_name' => 'A', 'angkatan' => 2024,
                'semester' => 'V', 'representative_id' => null,
            ])
            ->assertSessionHas('success');

        $class = ClassRoom::query()->firstOrFail();

        $this->assertSame(3, $class->practicums()->count());
        $this->assertSame(
            ['Audit Sistem Informasi', 'Kecerdasan Bisnis', 'Pemrograman Mobile'],
            Practicum::query()->with('course')->get()
                ->map(fn (Practicum $p) => $p->course->name)->sort()->values()->all(),
        );
    }

    public function test_satu_mahasiswa_tidak_boleh_menjadi_ketua_dua_kelas(): void
    {
        ['leader' => $leader] = $this->scenario();

        $this->actingAs($this->admin())
            ->post('/admin/kelas', [
                'class_name' => 'B', 'angkatan' => 2024,
                'semester' => 'V', 'representative_id' => $leader->id,
            ])
            ->assertSessionHasErrors('representative_id');
    }

    public function test_admin_dapat_membentuk_pasangan(): void
    {
        ['mobile' => $mobile, 'fajar' => $fajar] = $this->scenario();

        $sinta = $this->makeAsdos('Sinta', [$mobile]);

        $this->actingAs($this->admin())
            ->post('/admin/pasangan', [
                'course_id' => $mobile->id,
                'asdos_one_id' => $fajar->id,
                'asdos_two_id' => $sinta->id,
                'status' => 'aktif',
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseCount('asdos_pairs', 2);
    }

    /** Anggota harus sudah ditugaskan pada mata kuliah tersebut. */
    public function test_pasangan_menolak_asdos_yang_belum_ditugaskan_pada_matkul(): void
    {
        ['mobile' => $mobile, 'fajar' => $fajar] = $this->scenario();

        $audit = Course::query()->where('name', 'Audit Sistem Informasi')->firstOrFail();
        $tono = $this->makeAsdos('Tono', [$audit]);

        $this->actingAs($this->admin())
            ->post('/admin/pasangan', [
                'course_id' => $mobile->id,
                'asdos_one_id' => $fajar->id,
                'asdos_two_id' => $tono->id,
                'status' => 'aktif',
            ])
            ->assertSessionHasErrors('asdos_one_id');
    }

    public function test_pasangan_menolak_dua_asdos_yang_sama(): void
    {
        ['mobile' => $mobile, 'fajar' => $fajar] = $this->scenario();

        $this->actingAs($this->admin())
            ->post('/admin/pasangan', [
                'course_id' => $mobile->id,
                'asdos_one_id' => $fajar->id,
                'asdos_two_id' => $fajar->id,
                'status' => 'aktif',
            ])
            ->assertSessionHasErrors('asdos_one_id');
    }

    /** Pasangan yang sama tidak boleh didaftarkan dua kali, apa pun urutannya. */
    public function test_pasangan_duplikat_ditolak(): void
    {
        ['mobile' => $mobile, 'fajar' => $fajar, 'rina' => $rina] = $this->scenario();

        $this->actingAs($this->admin())
            ->post('/admin/pasangan', [
                'course_id' => $mobile->id,
                'asdos_one_id' => $rina->id,
                'asdos_two_id' => $fajar->id,
                'status' => 'aktif',
            ])
            ->assertSessionHasErrors('asdos_one_id');

        $this->assertDatabaseCount('asdos_pairs', 1);
    }

    public function test_admin_tidak_dapat_menghapus_pasangan_yang_sedang_mengampu(): void
    {
        ['leader' => $leader, 'pair' => $pair, 'practicums' => $practicums] = $this->scenario();

        $this->actingAs($leader)->post(
            "/perwakilan/praktikum/{$practicums['Pemrograman Mobile']->id}/booking",
            ['pair_id' => $pair->id],
        );

        $this->actingAs($this->admin())
            ->delete("/admin/pasangan/{$pair->id}")
            ->assertSessionHas('error');

        $this->assertDatabaseCount('asdos_pairs', 1);
    }
}
