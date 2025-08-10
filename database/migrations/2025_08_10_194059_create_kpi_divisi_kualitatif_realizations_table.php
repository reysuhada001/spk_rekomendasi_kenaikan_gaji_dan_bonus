<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('kpi_divisi_kualitatif_realizations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');       // karyawan
            $table->unsignedBigInteger('division_id');   // divisi karyawan
            $table->unsignedTinyInteger('bulan');
            $table->unsignedSmallInteger('tahun');
            $table->enum('status', ['submitted','approved','rejected','stale'])->default('submitted');
            $table->text('hr_note')->nullable();
            $table->decimal('total_score', 8, 2)->nullable(); // Σ(w*s) %
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index(['division_id','bulan','tahun'], 'idx_kdivkual_real_period');
            $table->index(['user_id','bulan','tahun'], 'idx_kdivkual_real_user_period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_divisi_kualitatif_realizations');
    }
};
    