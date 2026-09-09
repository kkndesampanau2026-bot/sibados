<?php

namespace Tests\Feature;

use App\Exports\BookingsExport;
use App\Models\Asdos;
use App\Models\AsdosPair;
use App\Models\ClassRoom;
use App\Models\Course;
use App\Models\User;
use App\Services\BookingService;
use App\Services\BookingSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/** Menguji ekspor Excel dan tampilan detail Asdos bagi ketua kelas. */
class ExportAndDetailTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::query()->create([
            'name' => 'Koordinator', 'email' => 'admin@export.test',
            'password' => 'password', 'role' => User::ROLE_ADMIN,
        ]);
    }

    private function makeAsdos(string $name, Course $course, string $phone): Asdos
    {
        $user = User::query()->create([
            'name' => $name,
            'email' => strtolower($name).'@export.test',
            'phone' => $phone,
            'password' => 'password',
            'role' => User::ROLE_ASDOS,
        ]);

        $asdos = Asdos::query()->create([
            'user_id' => $user->id,
            'nim' => (string) random_int(10000000, 99999999),
            'max_classes' => 3, 'status' => 'aktif',
        ]);

        $asdos->courses()->sync([$course->id => ['max_practicums' => 2]]);

        return $asdos;
    }

    /** @return array<string, mixed> */
    private function scenario(): array
    {
        $course = Course::query()->create([
            'name' => 'Pemrograman Mobile', 'code' => 'PMB', 'semester' => 'V', 'status' => 'aktif',
        ]);

        $leader = User::query()->create([
            'name' => 'Ahmad', 'email' => 'ahmad@export.test', 'phone' => '081200001111',
            'password' => 'password', 'role' => User::ROLE_PERWAKILAN,
        ]);

        $class = ClassRoom::query()->create([
            'class_name' => 'A', 'angkatan' => 2024, 'semester' => 'V',
            'representative_id' => $leader->id,
        ]);

        $practicum = $class->practicums()->create(['course_id' => $course->id, 'max_asdos' => 1]);

        $fajar = $this->makeAsdos('Fajar', $course, '081234567890');
        $rina = $this->makeAsdos('Rina', $course, '089876543210');

        $pair = AsdosPair::query()->create([
            'course_id' => $course->id, 'status' => 'aktif',
            ...AsdosPair::normalizeMembers($fajar->id, $rina->id),
        ]);

        app(BookingSettings::class)->set(['booking_open' => '1', 'booking_start' => '', 'booking_end' => '']);

        return compact('course', 'class', 'practicum', 'leader', 'pair', 'fajar', 'rina');
    }

    /*
    |--------------------------------------------------------------------------
    | Ekspor Excel
    |--------------------------------------------------------------------------
    */

    /** Unduhan sungguhan: berkas xlsx yang valid, bukan sekadar fake. */
    public function test_admin_dapat_mengunduh_berkas_excel(): void
    {
        ['practicum' => $practicum, 'pair' => $pair, 'leader' => $leader] = $this->scenario();

        app(BookingService::class)->book($practicum, $pair, $leader);

        $response = $this->actingAs($this->admin())->get('/admin/booking/export?status=aktif');

        $response->assertOk();
        $response->assertHeader(
            'content-type',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        );

        $this->assertMatchesRegularExpression(
            '/filename="?booking-asdos-aktif-\d{8}-\d{4}\.xlsx"?/',
            $response->headers->get('content-disposition') ?? '',
        );

        // XLSX adalah arsip ZIP; tanda tangannya harus "PK".
        $this->assertStringStartsWith('PK', $response->streamedContent());
    }

    public function test_nama_berkas_mengikuti_penyaring_status(): void
    {
        $response = $this->actingAs($this->admin())->get('/admin/booking/export?status=semua');

        $response->assertOk();
        $this->assertStringContainsString(
            'booking-asdos-semua-',
            $response->headers->get('content-disposition') ?? '',
        );
    }

    public function test_ekspor_menolak_ketua_kelas(): void
    {
        ['leader' => $leader] = $this->scenario();

        $this->actingAs($leader)->get('/admin/booking/export')->assertForbidden();
    }

    public function test_ekspor_menolak_tamu(): void
    {
        $this->get('/admin/booking/export')->assertRedirect('/login');
    }

    /** Isi berkas: satu baris judul + satu baris per booking, kolom sesuai urutan. */
    public function test_isi_ekspor_memuat_data_booking(): void
    {
        ['practicum' => $practicum, 'pair' => $pair, 'leader' => $leader] = $this->scenario();

        app(BookingService::class)->book($practicum, $pair, $leader);

        $export = new BookingsExport('aktif');
        $rows = $export->collection();

        $this->assertCount(1, $rows);

        $mapped = $export->map($rows->first());
        $headings = $export->headings();

        $this->assertCount(count($headings), $mapped);

        $data = array_combine($headings, $mapped);

        $this->assertSame(1, $data['No']);
        $this->assertSame(2024, $data['Angkatan']);
        $this->assertSame('A', $data['Kelas']);
        $this->assertSame('Pemrograman Mobile', $data['Mata Kuliah']);
        $this->assertSame('PMB', $data['Kode MK']);
        $this->assertSame('Fajar & Rina', $data['Pasangan Asdos']);
        $this->assertSame('Fajar', $data['Asdos 1']);
        $this->assertSame('Rina', $data['Asdos 2']);
        $this->assertSame('Ahmad', $data['Ketua Kelas']);
        $this->assertSame('Aktif', $data['Status']);
        $this->assertNotNull($data['Waktu Booking']);

        // NIM & nomor HP tetap berupa teks apa adanya (tanpa awalan apostrof);
        // value binder yang menjaga agar Excel tidak mengubahnya jadi angka.
        $this->assertMatchesRegularExpression('/^\d{8}$/', $data['NIM Asdos 1']);
        $this->assertSame('081200001111', $data['No. HP Ketua Kelas']);
    }

    public function test_ekspor_menghormati_penyaring_status_dan_mata_kuliah(): void
    {
        ['practicum' => $practicum, 'pair' => $pair, 'leader' => $leader, 'course' => $course] = $this->scenario();

        app(BookingService::class)->book($practicum, $pair, $leader);

        $this->assertCount(1, (new BookingsExport('aktif'))->collection());
        $this->assertCount(0, (new BookingsExport('dibatalkan'))->collection());
        $this->assertCount(1, (new BookingsExport('semua'))->collection());
        $this->assertCount(1, (new BookingsExport('aktif', $course->id))->collection());
        $this->assertCount(0, (new BookingsExport('aktif', $course->id + 999))->collection());
    }

    /** Penomoran baris dihitung per instance, tidak bocor antar ekspor. */
    public function test_penomoran_baris_dimulai_ulang_tiap_ekspor(): void
    {
        ['practicum' => $practicum, 'pair' => $pair, 'leader' => $leader] = $this->scenario();

        app(BookingService::class)->book($practicum, $pair, $leader);

        $first = new BookingsExport('aktif');
        $this->assertSame(1, $first->map($first->collection()->first())[0]);

        $second = new BookingsExport('aktif');
        $this->assertSame(1, $second->map($second->collection()->first())[0]);
    }

    /*
    |--------------------------------------------------------------------------
    | Detail Asdos untuk ketua kelas
    |--------------------------------------------------------------------------
    */

    public function test_ketua_kelas_melihat_detail_asdos_pada_halaman_pilih_pasangan(): void
    {
        ['leader' => $leader, 'practicum' => $practicum] = $this->scenario();

        $this->actingAs($leader)
            ->get("/perwakilan/praktikum/{$practicum->id}/booking")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Perwakilan/Booking')
                ->has('pairs', 1)
                ->has('pairs.0.members', 2)
                ->where('pairs.0.members.0.name', 'Fajar')
                ->where('pairs.0.members.0.email', 'fajar@export.test')
                ->where('pairs.0.members.0.phone', '081234567890')
                ->has('pairs.0.members.0.nim')
                ->where('pairs.0.members.1.name', 'Rina')
                ->where('pairs.0.members.1.email', 'rina@export.test')
                ->where('pairs.0.members.1.phone', '089876543210'));
    }

    /** Detail Asdos hanya terlihat oleh ketua kelas yang berhak. */
    public function test_detail_asdos_tidak_bocor_ke_kelas_lain(): void
    {
        ['practicum' => $practicum] = $this->scenario();

        $lain = User::query()->create([
            'name' => 'Bayu', 'email' => 'bayu@export.test',
            'password' => 'password', 'role' => User::ROLE_PERWAKILAN,
        ]);

        $this->actingAs($lain)
            ->get("/perwakilan/praktikum/{$practicum->id}/booking")
            ->assertForbidden();
    }

    /** Praktikum tanpa pasangan tidak menampilkan data kontak apa pun. */
    public function test_praktikum_tanpa_pasangan_tidak_memuat_kontak(): void
    {
        ['leader' => $leader, 'class' => $class] = $this->scenario();

        $lain = Course::query()->create([
            'name' => 'Kecerdasan Bisnis', 'semester' => 'V', 'status' => 'aktif',
        ]);
        $practicum = $class->practicums()->create(['course_id' => $lain->id, 'max_asdos' => 1]);

        $this->actingAs($leader)
            ->get("/perwakilan/praktikum/{$practicum->id}/booking")
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->has('pairs', 0));
    }
}
