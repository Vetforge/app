<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rations', function (Blueprint $table) {
            $table->integer('age_jours')->nullable()->after('age_velage');
            $table->string('sexe', 16)->nullable()->after('age_jours');
            $table->unsignedTinyInteger('parite')->nullable()->after('sexe');
            $table->decimal('poids_adulte', 7, 2)->nullable()->after('parite');
            $table->decimal('lait_potentiel', 7, 2)->nullable()->after('lait_potentiel305j');
            $table->unsignedTinyInteger('reference_bovine')->nullable()->after('poids_adulte');
        });
    }

    public function down(): void
    {
        Schema::table('rations', function (Blueprint $table) {
            $table->dropColumn(['age_jours', 'sexe', 'parite', 'poids_adulte', 'reference_bovine', 'lait_potentiel']);
        });
    }
};
