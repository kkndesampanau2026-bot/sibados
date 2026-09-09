<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Asdos\DashboardController as AsdosDashboard;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\Perwakilan\BookingController as PerwakilanBooking;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Autentikasi
|--------------------------------------------------------------------------
| Tidak ada pendaftaran publik — akun dibuat oleh Koordinator.
*/

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:6,1');
});

Route::post('/logout', [LoginController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

/** Mengarahkan setiap peran ke dashboard-nya masing-masing. */
Route::get('/', HomeController::class)->name('dashboard');

/*
|--------------------------------------------------------------------------
| Admin / Koordinator Asdos
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'role:admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/dashboard', Admin\DashboardController::class)->name('dashboard');

        // Master data
        Route::get('/mata-kuliah', [Admin\CourseController::class, 'index'])->name('courses.index');
        Route::post('/mata-kuliah', [Admin\CourseController::class, 'store'])->name('courses.store');
        Route::put('/mata-kuliah/{course}', [Admin\CourseController::class, 'update'])->name('courses.update');
        Route::delete('/mata-kuliah/{course}', [Admin\CourseController::class, 'destroy'])->name('courses.destroy');

        // Rombel (kelas) + praktikum yang diampunya
        Route::get('/kelas', [Admin\ClassRoomController::class, 'index'])->name('classes.index');
        Route::post('/kelas', [Admin\ClassRoomController::class, 'store'])->name('classes.store');
        Route::get('/kelas/{class}', [Admin\ClassRoomController::class, 'show'])->name('classes.show');
        Route::put('/kelas/{class}', [Admin\ClassRoomController::class, 'update'])->name('classes.update');
        Route::delete('/kelas/{class}', [Admin\ClassRoomController::class, 'destroy'])->name('classes.destroy');
        Route::post('/kelas/{class}/sinkron-praktikum', [Admin\ClassRoomController::class, 'sync'])->name('classes.sync');

        Route::post('/kelas/{class}/praktikum', [Admin\PracticumController::class, 'store'])->name('practicums.store');
        Route::put('/praktikum/{practicum}', [Admin\PracticumController::class, 'update'])->name('practicums.update');
        Route::delete('/praktikum/{practicum}', [Admin\PracticumController::class, 'destroy'])->name('practicums.destroy');
        Route::post('/praktikum/{practicum}/kunci', [Admin\PracticumController::class, 'toggleLock'])->name('practicums.lock');

        Route::get('/asdos', [Admin\AsdosController::class, 'index'])->name('asdos.index');
        Route::get('/asdos/{asdos}', [Admin\AsdosController::class, 'show'])->name('asdos.show');
        Route::post('/asdos', [Admin\AsdosController::class, 'store'])->name('asdos.store');
        Route::put('/asdos/{asdos}', [Admin\AsdosController::class, 'update'])->name('asdos.update');
        Route::delete('/asdos/{asdos}', [Admin\AsdosController::class, 'destroy'])->name('asdos.destroy');

        // Pasangan Asdos: dibentuk per mata kuliah, inilah yang dipilih ketua kelas.
        Route::get('/pasangan', [Admin\AsdosPairController::class, 'index'])->name('pairs.index');
        Route::post('/pasangan', [Admin\AsdosPairController::class, 'store'])->name('pairs.store');
        Route::put('/pasangan/{pair}', [Admin\AsdosPairController::class, 'update'])->name('pairs.update');
        Route::delete('/pasangan/{pair}', [Admin\AsdosPairController::class, 'destroy'])->name('pairs.destroy');

        Route::get('/pengguna', [Admin\UserController::class, 'index'])->name('users.index');
        Route::post('/pengguna', [Admin\UserController::class, 'store'])->name('users.store');
        Route::put('/pengguna/{user}', [Admin\UserController::class, 'update'])->name('users.update');
        Route::delete('/pengguna/{user}', [Admin\UserController::class, 'destroy'])->name('users.destroy');

        // Penempatan
        Route::get('/penempatan-matkul', [Admin\AssignmentController::class, 'index'])->name('assignments.index');
        Route::put('/penempatan-matkul/{asdos}', [Admin\AssignmentController::class, 'update'])->name('assignments.update');
        Route::post('/penempatan-matkul/{asdos}/toggle', [Admin\AssignmentController::class, 'toggle'])->name('assignments.toggle');
        Route::put('/penempatan-matkul/{asdos}/kuota', [Admin\AssignmentController::class, 'quota'])->name('assignments.quota');

        Route::get('/booking', [Admin\BookingController::class, 'index'])->name('bookings.index');
        Route::post('/booking', [Admin\BookingController::class, 'store'])->name('bookings.store');
        Route::put('/booking/{booking}', [Admin\BookingController::class, 'update'])->name('bookings.update');
        Route::delete('/booking/{booking}', [Admin\BookingController::class, 'destroy'])->name('bookings.destroy');
        Route::get('/booking/export', [Admin\BookingController::class, 'export'])->name('bookings.export');
        Route::get('/praktikum-belum-terisi', [Admin\BookingController::class, 'emptyPracticums'])->name('bookings.empty');
        Route::get('/riwayat', [Admin\BookingController::class, 'history'])->name('bookings.history');

        // Sistem
        Route::get('/pengaturan', [Admin\SettingController::class, 'edit'])->name('settings.edit');
        Route::put('/pengaturan', [Admin\SettingController::class, 'update'])->name('settings.update');
    });

/*
|--------------------------------------------------------------------------
| Profil pengguna
|--------------------------------------------------------------------------
| Dipakai bersama oleh Asisten Dosen dan Ketua Kelas. Data yang menjadi
| kewenangan Koordinator hanya ditampilkan, tidak dapat diubah dari sini.
*/

Route::middleware(['auth', 'role:asdos,perwakilan'])
    ->prefix('profil')
    ->name('profile.')
    ->group(function () {
        Route::get('/', [ProfileController::class, 'edit'])->name('edit');
        Route::put('/', [ProfileController::class, 'update'])->name('update');
        Route::put('/kata-sandi', [ProfileController::class, 'updatePassword'])->name('password');
    });

/*
|--------------------------------------------------------------------------
| Asisten Dosen
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'role:asdos'])
    ->prefix('asdos')
    ->name('asdos.')
    ->group(function () {
        Route::get('/dashboard', AsdosDashboard::class)->name('dashboard');
    });

/*
|--------------------------------------------------------------------------
| Ketua Kelas (Perwakilan)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'role:perwakilan'])
    ->prefix('perwakilan')
    ->name('perwakilan.')
    ->group(function () {
        Route::get('/dashboard', [PerwakilanBooking::class, 'index'])->name('dashboard');
        Route::get('/praktikum/{practicum}/booking', [PerwakilanBooking::class, 'show'])->name('booking.show');
        Route::post('/praktikum/{practicum}/booking', [PerwakilanBooking::class, 'store'])->name('booking.store');
    });
