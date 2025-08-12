<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('kpi_divisi_persentase_realizations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('kpi_divisi_id');
            $table->unsignedBigInteger('division_id');
            $table->unsignedTinyInteger('bulan');
            $table->unsignedSmallInteger('tahun');
            $table->decimal('target', 14, 4)->default(0);       // snapshot dari KPI Divisi
            $table->decimal('realization', 14, 4)->nullable();  // realisasi (persen)
            $table->decimal('score', 8, 2)->nullable();         // skor KPI (%)
            $table->enum('status', ['submitted','approved','rejected','stale'])->default('submitted');
            $table->text('hr_note')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index(['division_id','bulan','tahun'], 'idx_kdivpers_real_period');
            $table->index(['kpi_divisi_id'], 'idx_kdivpers_real_kpi');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_divisi_persentase_realizations');
    }
};
