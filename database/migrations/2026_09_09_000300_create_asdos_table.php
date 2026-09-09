<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asdos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('nim', 30)->unique();
            $table->string('semester', 10)->nullable();
            $table->string('photo_path')->nullable();
            // Kuota maksimal praktikum yang boleh diampu, dihitung per individu
            // walau Asdos tergabung dalam beberapa pasangan.
            $table->unsignedTinyInteger('max_classes')->default(3);
            $table->enum('status', ['aktif', 'nonaktif'])->default('aktif')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asdos');
    }
};
