<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('melanges', function (Blueprint $table) {
            $table->foreignId('ration_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            $table->dropForeign(['user_id']);
            $table->dropColumn('user_id');
        });

        Schema::dropIfExists('ration_melanges');
    }

    public function down(): void
    {
        Schema::create('ration_melanges', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ration_id')->constrained()->cascadeOnDelete();
            $table->foreignId('melange_id')->constrained()->cascadeOnDelete();
            $table->double('quantite')->nullable();
            $table->boolean('is_volonte')->default(false);
            $table->boolean('is_mb')->default(false);
            $table->timestamps();
        });

        Schema::table('melanges', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->dropForeign(['ration_id']);
            $table->dropColumn('ration_id');
        });
    }
};
