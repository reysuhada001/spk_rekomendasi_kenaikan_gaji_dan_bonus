<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('kpi_umum_realizations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('division_id')->nullable()->constrained('divisions')->nullOnDelete();
            $table->tinyInteger('bulan');       
            $table->integer('tahun');        
            $table->decimal('total_score', 10, 4)->default(0);
            $table->enum('status', ['submitted','approved','rejected','stale'])->default('submitted');
            $table->text('hr_note')->nullable();
            $table->timestamps();

            $table->unique(['user_id','bulan','tahun']);
            $table->index(['division_id','bulan','tahun','status']);
        });
    }
    public function down(): void {
        Schema::dropIfExists('kpi_umum_realizations');
    }
};
