<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ration_aliments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ration_id')->constrained()->cascadeOnDelete();
            $table->foreignId('aliment_id')->constrained()->cascadeOnDelete();
            $table->double('quantite');
            $table->boolean('is_volonte')->default(false);
            $table->boolean('is_mb')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ration_aliments');
    }
};
