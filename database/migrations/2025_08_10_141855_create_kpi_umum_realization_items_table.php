<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('kpi_umum_realization_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('realization_id')->constrained('kpi_umum_realizations')->cascadeOnDelete();
            $table->foreignId('kpi_umum_id')->constrained('kpi_umum')->cascadeOnDelete();
            $table->enum('tipe', ['kuantitatif','kualitatif','response','persentase']);
            $table->string('satuan')->nullable();
            $table->decimal('target', 15, 2)->default(0);
            $table->decimal('realisasi', 15, 2)->default(0);
            $table->decimal('score', 10, 4)->default(0); 
            $table->timestamps();

            $table->unique(['realization_id','kpi_umum_id']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('kpi_umum_realization_items');
    }
};
