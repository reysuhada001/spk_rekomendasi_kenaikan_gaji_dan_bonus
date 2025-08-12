<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('aspeks', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 255);
            $table->unsignedTinyInteger('bulan');
            $table->unsignedSmallInteger('tahun');
            $table->timestamps();

            // Kombinasi unik per periode
            $table->unique(['nama', 'bulan', 'tahun'], 'uniq_aspek_nama_periode');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aspeks');
    }
};
