<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Asisten Dosen bertugas BERPASANGAN. Pasangan dibentuk Koordinator
        // untuk satu mata kuliah tertentu; seorang Asdos boleh tergabung dalam
        // beberapa pasangan (mis. pasangan berbeda untuk mata kuliah berbeda).
        Schema::create('asdos_pairs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->foreignId('asdos_one_id')->constrained('asdos')->cascadeOnDelete();
            $table->foreignId('asdos_two_id')->constrained('asdos')->cascadeOnDelete();
            $table->enum('status', ['aktif', 'nonaktif'])->default('aktif')->index();
            $table->timestamps();

            // Id anggota selalu disimpan terurut (kecil dulu) sehingga pasangan
            // yang sama tidak dapat didaftarkan dua kali pada satu mata kuliah.
            $table->unique(['course_id', 'asdos_one_id', 'asdos_two_id'], 'asdos_pairs_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asdos_pairs');
    }
};
