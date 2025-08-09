<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('kpi_umum', function (Blueprint $table) {
            $table->id();
            $table->string('nama'); 
            $table->enum('tipe', ['kuantitatif','kualitatif','response','persentase']);
            $table->string('satuan')->nullable();
            $table->decimal('target', 15, 2)->default(0);
            $table->decimal('bobot', 10, 8)->nullable();
            $table->tinyInteger('bulan'); 
            $table->integer('tahun'); 
            $table->timestamps();

            $table->index(['tahun','bulan','tipe']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_umum');
    }
};