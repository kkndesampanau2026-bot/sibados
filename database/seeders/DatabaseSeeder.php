<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Models\User;
use App\Services\BookingSettings;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->firstOrCreate(
            ['email' => 'admin@sibados.test'],
            [
                'name' => 'Koordinator Asdos',
                'password' => 'password',
                'role' => User::ROLE_ADMIN,
                'is_active' => true,
            ],
        );

        foreach (BookingSettings::DEFAULTS as $key => $value) {
            Setting::query()->firstOrCreate(['key' => $key], ['value' => $value]);
        }

        $this->call([
            CourseSeeder::class,
            // Data contoh (tim, Asdos, perwakilan). Hapus baris ini untuk
            // instalasi bersih tanpa data demo.
            DemoSeeder::class,
        ]);
    }
}
