<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_rationnement_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('nom');
            $table->integer('effectif')->nullable();
            $table->integer('lait_potentiel305j')->nullable();
            $table->integer('poids_vif')->nullable();
            $table->integer('pourcentage_primipare')->nullable();
            $table->double('nec')->nullable();
            $table->double('tb_annuel')->nullable();
            $table->double('tp_annuel')->nullable();
            $table->string('activite')->nullable();
            $table->double('temperature_ambiante')->nullable();
            $table->double('nec_velage')->nullable();
            $table->integer('ivv')->nullable();
            $table->integer('poids_veau_naissance')->nullable();
            $table->integer('age_velage')->nullable();
            $table->integer('lait_objectif305j')->nullable();
            $table->integer('stade_moyen')->nullable();
            $table->integer('lait_objectif')->nullable();
            $table->boolean('is_ration_semi_complete')->nullable();
            $table->double('ecart_variation_reserve')->nullable();
            $table->double('strategie')->nullable();
            $table->integer('lait_objectif_auge')->nullable();
            $table->string('race')->nullable();
            $table->integer('mois_lactation')->nullable();
            $table->integer('mois_gestation')->nullable();
            $table->string('categorie_animal')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rations');
    }
};
