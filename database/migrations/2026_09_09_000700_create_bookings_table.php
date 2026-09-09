<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Yang dibooking adalah PASANGAN Asdos untuk satu praktikum.
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('practicum_id')->constrained('practicums')->cascadeOnDelete();
            $table->foreignId('pair_id')->constrained('asdos_pairs')->cascadeOnDelete();
            $table->foreignId('representative_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['aktif', 'dibatalkan'])->default('aktif')->index();
            $table->timestamp('booked_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestamps();

            // Kolom penanda booking aktif: bernilai 1 saat aktif, NULL saat dibatalkan.
            // MySQL mengabaikan NULL pada unique index, sehingga satu pasangan
            // hanya boleh punya SATU baris aktif per praktikum, tetapi riwayat
            // pembatalan tetap boleh berulang. Ini proteksi lapisan database
            // terhadap double booking / race condition.
            $table->unsignedTinyInteger('active_flag')->nullable();
            $table->unique(['practicum_id', 'pair_id', 'active_flag'], 'bookings_active_unique');

            $table->index(['pair_id', 'status']);
            $table->index(['practicum_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
