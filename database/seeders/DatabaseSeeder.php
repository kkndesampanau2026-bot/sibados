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
        // Kredensial admin dibaca dari config/sibados.php agar deployment
        // produksi tidak memakai kata sandi bawaan yang mudah ditebak.
        User::query()->firstOrCreate(
            ['email' => config('sibados.admin.email')],
            [
                'name' => config('sibados.admin.name'),
                'password' => config('sibados.admin.password'),
                'role' => User::ROLE_ADMIN,
                'is_active' => true,
            ],
        );

        foreach (BookingSettings::DEFAULTS as $key => $value) {
            Setting::query()->firstOrCreate(['key' => $key], ['value' => $value]);
        }

        $this->call(CourseSeeder::class);

        // Akun demo berkata sandi seragam; jangan pernah dibuat di produksi.
        if (! app()->environment('production')) {
            $this->call(DemoSeeder::class);
        }
    }
}
