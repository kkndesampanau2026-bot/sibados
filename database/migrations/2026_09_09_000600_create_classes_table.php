<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Rombel mahasiswa, mis. "Kelas A angkatan 2024 (semester V)".
        // Satu rombel punya SATU ketua kelas dan mengampu beberapa praktikum.
        Schema::create('classes', function (Blueprint $table) {
            $table->id();
            $table->string('class_name', 10);
            $table->unsignedSmallInteger('angkatan')->index();
            // Semester berjalan rombel ini; dipakai untuk mencocokkan mata kuliah.
            $table->string('semester', 10)->index();
            $table->foreignId('representative_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['angkatan', 'class_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('classes');
    }
};
