<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('kpi_divisi_distribution_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('distribution_id')->constrained('kpi_divisi_distributions')->cascadeOnDelete();
            $table->foreignId('kpi_divisi_id')->constrained('kpi_divisi')->cascadeOnDelete(); // hanya tipe=kuantitatif
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete(); // karyawan divisi
            $table->decimal('target', 15, 2)->default(0);

            $table->timestamps();
            $table->unique(['distribution_id','kpi_divisi_id','user_id'], 'uq_dist_item');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kpi_divisi_distribution_items');
    }
};
