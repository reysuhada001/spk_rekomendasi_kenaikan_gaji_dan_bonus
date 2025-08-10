<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('kpi_divisi_kuantitatif_realizations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');       
            $table->unsignedBigInteger('division_id');  
            $table->unsignedTinyInteger('bulan');      
            $table->unsignedSmallInteger('tahun');     
            $table->decimal('total_score', 8, 2)->nullable(); 
            $table->enum('status', ['submitted', 'approved', 'rejected','stale'])->default('submitted');
            $table->text('hr_note')->nullable();
            $table->unsignedBigInteger('created_by')->nullable(); 
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['user_id','division_id','bulan','tahun'], 'uniq_real_kdivq');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_divisi_kuantitatif_realizations');
    }
};
