<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('breeder_id')->constrained()->cascadeOnDelete();
            $table->string('animal_nom')->nullable();
            $table->string('module');
            $table->string('status')->default('complete');
            $table->date('sampled_at')->nullable();
            $table->date('analyzed_at')->nullable();
            $table->string('intervenant')->nullable();
            $table->json('payload');
            $table->json('results')->nullable();
            $table->json('settings_snapshot')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'module']);
            $table->index(['breeder_id', 'module']);
            $table->index(['analyzed_at', 'sampled_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('analyses');
    }
};
