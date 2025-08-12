<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('peer_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessor_id')->constrained('users')->cascadeOnDelete(); // penilai
            $table->foreignId('assessee_id')->constrained('users')->cascadeOnDelete(); // yang dinilai
            $table->foreignId('division_id')->constrained('divisions')->cascadeOnDelete();
            $table->unsignedTinyInteger('bulan');   // 1..12
            $table->unsignedSmallInteger('tahun');  // 2000..2100
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();

            $table->unique(['assessor_id','assessee_id','bulan','tahun'], 'uniq_peer_by_period');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('peer_assessments');
    }
};
