<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Akun Koordinator awal
    |--------------------------------------------------------------------------
    | Dipakai saat mengisi data awal pada database kosong. Nilainya dibaca dari
    | berkas config (bukan env() langsung) agar tetap benar meski konfigurasi
    | sudah di-cache di produksi.
    */

    'admin' => [
        'name' => env('ADMIN_NAME', 'Koordinator Asdos'),
        'email' => env('ADMIN_EMAIL', 'admin@sibados.test'),
        'password' => env('ADMIN_PASSWORD', 'password'),
    ],

];
