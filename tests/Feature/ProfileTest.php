<?php

namespace Tests\Feature;

use App\Models\Asdos;
use App\Models\AsdosPair;
use App\Models\ClassRoom;
use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/** Halaman profil untuk Asisten Dosen dan Ketua Kelas. */
class ProfileTest extends TestCase
{
    use RefreshDatabase;

    private function leader(): User
    {
        $leader = User::query()->create([
            'name' => 'Ahmad', 'email' => 'ahmad@profil.test', 'phone' => '081200001111',
            'password' => 'password', 'role' => User::ROLE_PERWAKILAN,
        ]);

        $course = Course::query()->create([
            'name' => 'Pemrograman Mobile', 'semester' => 'V', 'status' => 'aktif',
        ]);

        $class = ClassRoom::query()->create([
            'class_name' => 'A', 'angkatan' => 2024, 'semester' => 'V',
            'representative_id' => $leader->id,
        ]);

        $class->practicums()->create(['course_id' => $course->id, 'max_asdos' => 1]);

        return $leader;
    }

    private function asdos(): Asdos
    {
        $course = Course::query()->create([
            'name' => 'Kecerdasan Bisnis', 'semester' => 'V', 'status' => 'aktif',
        ]);

        $user = User::query()->create([
            'name' => 'Fajar', 'email' => 'fajar@profil.test', 'phone' => '081234567890',
            'password' => 'password', 'role' => User::ROLE_ASDOS,
        ]);

        $asdos = Asdos::query()->create([
            'user_id' => $user->id, 'nim' => '12345678',
            'semester' => 'V', 'max_classes' => 3, 'status' => 'aktif',
        ]);

        $asdos->courses()->sync([$course->id => ['max_practicums' => 2]]);

        $partnerUser = User::query()->create([
            'name' => 'Rina', 'email' => 'rina@profil.test',
            'password' => 'password', 'role' => User::ROLE_ASDOS,
        ]);
        $partner = Asdos::query()->create([
            'user_id' => $partnerUser->id, 'nim' => '87654321',
            'max_classes' => 3, 'status' => 'aktif',
        ]);
        $partner->courses()->sync([$course->id => ['max_practicums' => 2]]);

        AsdosPair::query()->create([
            'course_id' => $course->id, 'status' => 'aktif',
            ...AsdosPair::normalizeMembers($asdos->id, $partner->id),
        ]);

        return $asdos;
    }

    /*
    |--------------------------------------------------------------------------
    | Akses
    |--------------------------------------------------------------------------
    */

    public function test_tamu_diarahkan_ke_login(): void
    {
        $this->get('/profil')->assertRedirect('/login');
    }

    /** Koordinator tidak memiliki halaman profil di sini. */
    public function test_admin_tidak_memiliki_halaman_profil(): void
    {
        $admin = User::query()->create([
            'name' => 'Koordinator', 'email' => 'admin@profil.test',
            'password' => 'password', 'role' => User::ROLE_ADMIN,
        ]);

        $this->actingAs($admin)->get('/profil')->assertForbidden();
    }

    /*
    |--------------------------------------------------------------------------
    | Tampilan
    |--------------------------------------------------------------------------
    */

    public function test_ketua_kelas_melihat_profil_dan_kelasnya(): void
    {
        $leader = $this->leader();

        $this->actingAs($leader)
            ->get('/profil')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Profile/Edit')
                ->where('profile.name', 'Ahmad')
                ->where('profile.email', 'ahmad@profil.test')
                ->where('profile.phone', '081200001111')
                ->where('profile.role', 'perwakilan')
                ->where('context.type', 'perwakilan')
                ->where('context.ready', true)
                ->where('context.label', 'Kelas A Angkatan 2024')
                ->where('context.total', 1)
                ->has('context.practicums', 1));
    }

    public function test_asdos_melihat_profil_beserta_kuota_dan_pasangan(): void
    {
        $asdos = $this->asdos();

        $this->actingAs($asdos->user)
            ->get('/profil')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Profile/Edit')
                ->where('profile.name', 'Fajar')
                ->where('profile.role', 'asdos')
                ->where('context.type', 'asdos')
                ->where('context.ready', true)
                ->where('context.nim', '12345678')
                ->where('context.max_classes', 3)
                ->has('context.courses', 1)
                ->where('context.courses.0.max', 2)
                ->has('context.pairs', 1)
                ->where('context.pairs.0.partner', 'Rina'));
    }

    /** Ketua kelas yang belum memimpin rombel tetap dapat membuka profilnya. */
    public function test_ketua_kelas_tanpa_rombel_tetap_dapat_membuka_profil(): void
    {
        $user = User::query()->create([
            'name' => 'Bayu', 'email' => 'bayu@profil.test',
            'password' => 'password', 'role' => User::ROLE_PERWAKILAN,
        ]);

        $this->actingAs($user)
            ->get('/profil')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->where('context.ready', false));
    }

    /*
    |--------------------------------------------------------------------------
    | Ubah data diri
    |--------------------------------------------------------------------------
    */

    public function test_pengguna_dapat_memperbarui_data_dirinya(): void
    {
        $leader = $this->leader();

        $this->actingAs($leader)
            ->put('/profil', [
                'name' => 'Ahmad Fauzan',
                'email' => 'ahmad.baru@profil.test',
                'phone' => '089900001111',
            ])
            ->assertSessionHas('success');

        $leader->refresh();

        $this->assertSame('Ahmad Fauzan', $leader->name);
        $this->assertSame('ahmad.baru@profil.test', $leader->email);
        $this->assertSame('089900001111', $leader->phone);
    }

    public function test_email_harus_unik_antar_pengguna(): void
    {
        $leader = $this->leader();
        $asdos = $this->asdos();

        $this->actingAs($leader)
            ->put('/profil', ['name' => 'Ahmad', 'email' => $asdos->user->email, 'phone' => null])
            ->assertSessionHasErrors('email');

        $this->assertSame('ahmad@profil.test', $leader->fresh()->email);
    }

    /** Email sendiri tidak dianggap bentrok saat menyimpan tanpa mengubahnya. */
    public function test_menyimpan_tanpa_mengubah_email_tidak_dianggap_bentrok(): void
    {
        $leader = $this->leader();

        $this->actingAs($leader)
            ->put('/profil', ['name' => 'Ahmad', 'email' => 'ahmad@profil.test', 'phone' => '0812'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');
    }

    /** NIM adalah kewenangan Koordinator; kiriman apa pun diabaikan. */
    public function test_asdos_tidak_dapat_mengubah_nim_lewat_profil(): void
    {
        $asdos = $this->asdos();

        $this->actingAs($asdos->user)
            ->put('/profil', [
                'name' => 'Fajar', 'email' => 'fajar@profil.test', 'phone' => '0812',
                'nim' => '99999999', 'max_classes' => 99,
            ])
            ->assertSessionHas('success');

        $asdos->refresh();

        $this->assertSame('12345678', $asdos->nim);
        $this->assertSame(3, $asdos->max_classes);
    }

    /*
    |--------------------------------------------------------------------------
    | Ganti kata sandi
    |--------------------------------------------------------------------------
    */

    public function test_pengguna_dapat_mengganti_kata_sandi(): void
    {
        $leader = $this->leader();

        $this->actingAs($leader)
            ->put('/profil/kata-sandi', [
                'current_password' => 'password',
                'password' => 'sandi-baru-123',
                'password_confirmation' => 'sandi-baru-123',
            ])
            ->assertSessionHas('success');

        $this->assertTrue(Hash::check('sandi-baru-123', $leader->fresh()->password));
    }

    public function test_kata_sandi_lama_harus_benar(): void
    {
        $leader = $this->leader();

        $this->actingAs($leader)
            ->put('/profil/kata-sandi', [
                'current_password' => 'salah',
                'password' => 'sandi-baru-123',
                'password_confirmation' => 'sandi-baru-123',
            ])
            ->assertSessionHasErrors('current_password');

        $this->assertTrue(Hash::check('password', $leader->fresh()->password));
    }

    public function test_konfirmasi_kata_sandi_harus_cocok(): void
    {
        $leader = $this->leader();

        $this->actingAs($leader)
            ->put('/profil/kata-sandi', [
                'current_password' => 'password',
                'password' => 'sandi-baru-123',
                'password_confirmation' => 'beda-sekali',
            ])
            ->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('password', $leader->fresh()->password));
    }

    public function test_kata_sandi_baru_minimal_delapan_karakter(): void
    {
        $leader = $this->leader();

        $this->actingAs($leader)
            ->put('/profil/kata-sandi', [
                'current_password' => 'password',
                'password' => 'pendek',
                'password_confirmation' => 'pendek',
            ])
            ->assertSessionHasErrors('password');
    }
}
