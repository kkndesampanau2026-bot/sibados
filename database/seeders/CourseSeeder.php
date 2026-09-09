<?php

namespace Database\Seeders;

use App\Models\ClassRoom;
use App\Models\Course;
use Illuminate\Database\Seeder;

/**
 * Mata kuliah praktikum, rombel per angkatan, dan praktikum yang diampunya.
 *
 * Setiap angkatan berada pada satu semester dan mengampu seluruh mata kuliah
 * praktikum semester tersebut. Contoh: angkatan 2024 (semester V) mengampu
 * Pemrograman Mobile, Kecerdasan Bisnis, dan Audit Sistem Informasi.
 */
class CourseSeeder extends Seeder
{
    /** @var array<int, array{name: string, code: string, semester: string}> */
    private const COURSES = [
        ['name' => 'Algoritma & Pemrograman', 'code' => 'ALP', 'semester' => 'I'],
        ['name' => 'Logika Diskrit', 'code' => 'LGD', 'semester' => 'I'],
        ['name' => 'Kecerdasan Artifisial', 'code' => 'KAI', 'semester' => 'III'],
        ['name' => 'Rekayasa Perangkat Lunak', 'code' => 'RPL', 'semester' => 'III'],
        ['name' => 'Pemrograman Mobile', 'code' => 'PMB', 'semester' => 'V'],
        ['name' => 'Kecerdasan Bisnis', 'code' => 'KBS', 'semester' => 'V'],
        ['name' => 'Audit Sistem Informasi', 'code' => 'ASI', 'semester' => 'V'],
    ];

    /** Angkatan => semester berjalan. */
    private const COHORTS = [
        2024 => 'V',
        2025 => 'III',
        2026 => 'I',
    ];

    private const CLASSES = ['A', 'B', 'C'];

    public function run(): void
    {
        foreach (self::COURSES as $data) {
            Course::query()->firstOrCreate(
                ['name' => $data['name'], 'semester' => $data['semester']],
                ['code' => $data['code'], 'status' => 'aktif'],
            );
        }

        foreach (self::COHORTS as $angkatan => $semester) {
            $courses = Course::query()->where('semester', $semester)->get();

            foreach (self::CLASSES as $className) {
                $class = ClassRoom::query()->firstOrCreate(
                    ['angkatan' => $angkatan, 'class_name' => $className],
                    ['semester' => $semester],
                );

                // Setiap rombel mengampu seluruh praktikum semesternya.
                foreach ($courses as $course) {
                    $class->practicums()->firstOrCreate(
                        ['course_id' => $course->id],
                        ['max_asdos' => 1, 'is_locked' => false],
                    );
                }
            }
        }
    }
}
