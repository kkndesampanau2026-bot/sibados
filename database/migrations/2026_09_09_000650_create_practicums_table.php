<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Satu praktikum = satu rombel mengampu satu mata kuliah.
        // Inilah unit yang dibooking: ketua Kelas A angkatan 2024 memilih Asdos
        // untuk masing-masing praktikumnya (Pemrograman Mobile, Kecerdasan
        // Bisnis, Audit Sistem Informasi).
        Schema::create('practicums', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            // Kuota Asdos per praktikum (mendukung multi-Asdos bila diperlukan).
            $table->unsignedTinyInteger('max_asdos')->default(1);
            // Lock Booking: praktikum dikunci, hanya Admin yang dapat mengubah.
            $table->boolean('is_locked')->default(false);
            $table->timestamps();

            $table->unique(['class_id', 'course_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('practicums');
    }
};
