<?php

namespace Database\Seeders;

use App\Models\Asdos;
use App\Models\AsdosPair;
use App\Models\ClassRoom;
use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Data contoh agar sistem bisa langsung dicoba: Asdos, pasangan Asdos per mata
 * kuliah, dan satu ketua kelas untuk tiap rombel. Hapus pemanggilannya di
 * DatabaseSeeder untuk instalasi bersih.
 */
class DemoSeeder extends Seeder
{
    private const PASSWORD = 'password';

    /**
     * Mata kuliah yang boleh ditangani tiap Asdos, beserta KUOTA per mata
     * kuliah: berapa praktikum matkul itu yang boleh diampu. Kuota inilah yang
     * membatasi berapa kelas dapat memilih Asdos yang sama.
     *
     * @var array<string, array<string, int>>
     */
    private const ASSIGNMENTS = [
        'Andi' => ['Algoritma & Pemrograman' => 2, 'Logika Diskrit' => 2],
        'Budi' => ['Algoritma & Pemrograman' => 2, 'Logika Diskrit' => 2],
        'Citra' => ['Kecerdasan Artifisial' => 2, 'Rekayasa Perangkat Lunak' => 2],
        'Dimas' => ['Kecerdasan Artifisial' => 2, 'Rekayasa Perangkat Lunak' => 2],
        'Fajar' => ['Pemrograman Mobile' => 2, 'Kecerdasan Bisnis' => 2],
        'Rina' => ['Pemrograman Mobile' => 2, 'Kecerdasan Bisnis' => 2],
        'Sinta' => ['Audit Sistem Informasi' => 2, 'Pemrograman Mobile' => 1],
        'Tono' => ['Audit Sistem Informasi' => 2, 'Kecerdasan Bisnis' => 1],
    ];

    /**
     * Pasangan yang dibentuk Koordinator: [mata kuliah, asdos 1, asdos 2].
     * Perhatikan Fajar & Rina berpasangan di dua mata kuliah berbeda, dan
     * Sinta muncul di beberapa pasangan yang berbeda anggotanya.
     *
     * @var array<int, array{0: string, 1: string, 2: string}>
     */
    private const PAIRS = [
        ['Algoritma & Pemrograman', 'Andi', 'Budi'],
        ['Logika Diskrit', 'Andi', 'Budi'],
        ['Kecerdasan Artifisial', 'Citra', 'Dimas'],
        ['Rekayasa Perangkat Lunak', 'Citra', 'Dimas'],
        ['Pemrograman Mobile', 'Fajar', 'Rina'],
        ['Pemrograman Mobile', 'Sinta', 'Fajar'],
        ['Kecerdasan Bisnis', 'Fajar', 'Rina'],
        ['Kecerdasan Bisnis', 'Tono', 'Rina'],
        ['Audit Sistem Informasi', 'Sinta', 'Tono'],
    ];

    /** Nama ketua kelas per rombel: "angkatan-kelas" => nama. */
    private const LEADERS = [
        '2024-A' => 'Ahmad',
        '2024-B' => 'Bayu',
        '2024-C' => 'Cindy',
        '2025-A' => 'Dewi',
        '2025-B' => 'Eka',
        '2025-C' => 'Farhan',
        '2026-A' => 'Gilang',
        '2026-B' => 'Hana',
        '2026-C' => 'Irfan',
    ];

    public function run(): void
    {
        $asdosByName = $this->seedAsdos();
        $this->seedPairs($asdosByName);
        $this->seedLeaders();
    }

    /** @return array<string, Asdos> */
    private function seedAsdos(): array
    {
        $nim = 12345670;
        $asdosByName = [];

        foreach (self::ASSIGNMENTS as $name => $courseQuotas) {
            $nim++;

            $user = User::query()->firstOrCreate(
                ['email' => strtolower($name).'.asdos@sibados.test'],
                [
                    'name' => $name,
                    'phone' => '08120000'.substr((string) $nim, -4),
                    'password' => self::PASSWORD,
                    'role' => User::ROLE_ASDOS,
                    'is_active' => true,
                ],
            );

            $asdos = Asdos::query()->firstOrCreate(
                ['user_id' => $user->id],
                [
                    'nim' => (string) $nim,
                    'semester' => 'V',
                    'max_classes' => 3,
                    'status' => 'aktif',
                ],
            );

            // Penempatan mata kuliah + kuota per mata kuliah.
            $asdos->courses()->sync(
                Course::query()
                    ->whereIn('name', array_keys($courseQuotas))
                    ->get()
                    ->mapWithKeys(fn (Course $c) => [
                        $c->id => ['max_practicums' => $courseQuotas[$c->name]],
                    ])
                    ->all(),
            );

            $asdosByName[$name] = $asdos;
        }

        return $asdosByName;
    }

    /** @param array<string, Asdos> $asdosByName */
    private function seedPairs(array $asdosByName): void
    {
        foreach (self::PAIRS as [$courseName, $first, $second]) {
            $course = Course::query()->where('name', $courseName)->first();

            if (! $course || ! isset($asdosByName[$first], $asdosByName[$second])) {
                continue;
            }

            $members = AsdosPair::normalizeMembers(
                $asdosByName[$first]->id,
                $asdosByName[$second]->id,
            );

            AsdosPair::query()->firstOrCreate(
                ['course_id' => $course->id, ...$members],
                ['status' => 'aktif'],
            );
        }
    }

    /** Satu ketua kelas untuk tiap rombel. */
    private function seedLeaders(): void
    {
        foreach (self::LEADERS as $key => $name) {
            [$angkatan, $className] = explode('-', $key);

            $class = ClassRoom::query()
                ->where('angkatan', (int) $angkatan)
                ->where('class_name', $className)
                ->first();

            if (! $class) {
                continue;
            }

            $slug = strtolower($name).'.'.$angkatan.strtolower($className);

            $user = User::query()->firstOrCreate(
                ['email' => "{$slug}@sibados.test"],
                [
                    'name' => $name,
                    'phone' => '08130000'.$angkatan,
                    'password' => self::PASSWORD,
                    'role' => User::ROLE_PERWAKILAN,
                    'is_active' => true,
                ],
            );

            $class->update(['representative_id' => $user->id]);
        }
    }
}
