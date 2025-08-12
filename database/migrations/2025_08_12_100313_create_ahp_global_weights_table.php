<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ahp_global_weights', function (Blueprint $table) {
            $table->id();
            // bobot 3 kriteria (jumlah = 1.0)
            $table->decimal('w_kpi_umum', 10, 8)->nullable();
            $table->decimal('w_kpi_divisi', 10, 8)->nullable();
            $table->decimal('w_peer', 10, 8)->nullable(); // penilaian karyawan (peer)
            // metrik perhitungan
            $table->decimal('lambda_max', 12, 8)->nullable();
            $table->decimal('ci', 12, 8)->nullable();
            $table->decimal('cr', 12, 8)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ahp_global_weights');
    }
};
