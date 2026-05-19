<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('melange_aliments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('melange_id')->constrained()->cascadeOnDelete();
            $table->foreignId('aliment_id')->constrained()->cascadeOnDelete();
            $table->double('quantite');
            $table->boolean('is_mb')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('melange_aliments');
    }
};
