<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('kpi_divisi_kualitatif_realization_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('realization_id');
            $table->unsignedBigInteger('kpi_divisi_id');     // KPI bertipe kualitatif
            $table->decimal('target', 14, 4)->default(0);     // copy dari kpi_divisi.target
            $table->decimal('realization', 14, 4)->default(0);
            $table->decimal('score', 8, 2)->nullable();       // skor per KPI (%)
            $table->timestamps();

            $table->index(['realization_id','kpi_divisi_id'], 'idx_kdivkual_real_item');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_divisi_kualitatif_realization_items');
    }
};
