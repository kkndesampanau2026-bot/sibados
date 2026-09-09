<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Penempatan Mata Kuliah Asdos: menentukan siapa boleh menangani apa,
        // sekaligus KUOTA PER MATA KULIAH — berapa praktikum pada mata kuliah
        // tersebut yang boleh diampu Asdos ini. Kuota inilah yang membatasi
        // berapa kelas dapat memilih Asdos yang sama untuk satu mata kuliah.
        Schema::create('course_asdos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignId('asdos_id')->constrained('asdos')->cascadeOnDelete();
            $table->unsignedTinyInteger('max_practicums')->default(2);
            $table->timestamps();

            $table->unique(['course_id', 'asdos_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('course_asdos');
    }
};
