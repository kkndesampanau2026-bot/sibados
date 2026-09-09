<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

/**
 * Mengisi data awal hanya pada database yang benar-benar masih kosong.
 *
 * Dipanggil setiap container start, sehingga harus aman dijalankan berulang:
 * begitu ada satu pengguna pun, perintah ini tidak melakukan apa-apa.
 */
class SeedInitialData extends Command
{
    protected $signature = 'sibados:seed-initial';

    protected $description = 'Mengisi data awal bila database masih kosong';

    public function handle(): int
    {
        if (User::query()->exists()) {
            $this->info('Data awal sudah ada — seeding dilewati.');

            return self::SUCCESS;
        }

        $this->info('Database masih kosong — mengisi data awal…');
        $this->call('db:seed', ['--force' => true]);

        return self::SUCCESS;
    }
}
