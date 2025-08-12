<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('peer_assessment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained('peer_assessments')->cascadeOnDelete();
            $table->foreignId('aspek_id')->constrained('aspeks')->cascadeOnDelete();
            $table->unsignedTinyInteger('score'); // 1..10
            $table->timestamps();

            $table->unique(['assessment_id','aspek_id'], 'uniq_peer_item');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('peer_assessment_items');
    }
};
