<?php

use App\Enums\CategorieAnimal;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rations', function (Blueprint $table) {
            // Gain / stade physiologique (bovins croissance, agneaux, chevrettes).
            $table->integer('gmq')->nullable()->after('categorie_animal');            // gain moyen quotidien de l'animal, g/j
            $table->string('stade_physiologique')->nullable()->after('gmq');           // entretien|gestation|lactation|croissance|engraissement
            $table->integer('jours_gestation')->nullable()->after('stade_physiologique');
            $table->integer('jours_lactation')->nullable()->after('jours_gestation');

            // Portée (ovins allaitants) / nombre de jeunes (caprins).
            $table->integer('nombre_jeunes')->nullable()->after('jours_lactation');     // taille de portée / nb chevreaux
            $table->double('poids_portee')->nullable()->after('nombre_jeunes');         // BWlit, kg
            $table->integer('gmq_portee')->nullable()->after('poids_portee');           // ADGlit, g/j

            // Composition du lait (chèvre / brebis laitière).
            $table->double('mfc')->nullable()->after('gmq_portee');                     // taux butyreux, g/kg
            $table->double('mpc')->nullable()->after('mfc');                            // taux protéique, g/kg

            // Orientation de production ovine (lait | viande).
            $table->string('type_production_ovin')->nullable()->after('mpc');
        });

        // Normaliser les valeurs historiques de categorie_animal vers les valeurs canoniques de l'enum.
        DB::table('rations')
            ->whereNotNull('categorie_animal')
            ->orderBy('id')
            ->select(['id', 'categorie_animal'])
            ->chunkById(200, function ($rations) {
                foreach ($rations as $ration) {
                    $canonical = CategorieAnimal::fromLoose($ration->categorie_animal)->value;

                    if ($canonical !== $ration->categorie_animal) {
                        DB::table('rations')->where('id', $ration->id)->update([
                            'categorie_animal' => $canonical,
                        ]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('rations', function (Blueprint $table) {
            $table->dropColumn([
                'gmq',
                'stade_physiologique',
                'jours_gestation',
                'jours_lactation',
                'nombre_jeunes',
                'poids_portee',
                'gmq_portee',
                'mfc',
                'mpc',
                'type_production_ovin',
            ]);
        });
    }
};
