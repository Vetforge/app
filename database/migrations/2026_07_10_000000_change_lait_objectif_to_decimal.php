<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rations', function (Blueprint $table) {
            // Le formulaire accepte 0,1 kg/j : une colonne entière tronquait la précision
            // (2,5 → 2, 3,5 → 3) après persistance PostgreSQL (cf. FOR-06).
            $table->decimal('lait_objectif', 6, 2)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('rations', function (Blueprint $table) {
            $table->integer('lait_objectif')->nullable()->change();
        });
    }
};
