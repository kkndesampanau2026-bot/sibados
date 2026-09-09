<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code', 30)->nullable()->unique();
            // Semester ditulis dalam angka romawi sesuai PRD: I, III, V.
            $table->string('semester', 10)->index();
            $table->enum('status', ['aktif', 'nonaktif'])->default('aktif')->index();
            $table->timestamps();

            $table->unique(['name', 'semester']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('courses');
    }
};
