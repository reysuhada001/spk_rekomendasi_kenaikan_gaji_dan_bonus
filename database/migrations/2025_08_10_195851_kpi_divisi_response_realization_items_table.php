<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('kpi_divisi_response_realization_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('realization_id')
                ->constrained('kpi_divisi_response_realizations')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->unsignedBigInteger('kpi_divisi_id');     
            $table->decimal('target', 14, 4)->default(0);     
            $table->decimal('realization', 14, 4)->default(0); 
            $table->decimal('score', 8, 2)->nullable();       
            $table->timestamps();

            $table->index(['realization_id','kpi_divisi_id'], 'idx_kdivresp_real_item');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_divisi_response_realization_items');
    }
};
