<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('kpi_divisi_distributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('division_id')->constrained('divisions')->cascadeOnDelete();
            $table->tinyInteger('bulan');
            $table->integer('tahun');
            $table->enum('status', ['submitted','approved','rejected','stale'])->default('submitted');
            $table->text('hr_note')->nullable();
            $table->foreignId('created_by')->constrained('users'); 
            $table->timestamps();

            $table->unique(['division_id','bulan','tahun']); 
            $table->index(['status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_divisi_distributions');
    }
};
