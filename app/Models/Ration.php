<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CategorieAnimal;
use Database\Factories\RationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Ration extends Model
{
    /** @use HasFactory<RationFactory> */
    use HasFactory;

    protected $fillable = [
        'plan_rationnement_id', 'nom', 'effectif', 'lait_potentiel305j', 'poids_vif',
        'pourcentage_primipare', 'nec', 'tb_annuel', 'tp_annuel', 'activite',
        'temperature_ambiante', 'nec_velage', 'ivv', 'poids_veau_naissance', 'age_velage',
        'lait_objectif305j', 'stade_moyen', 'lait_objectif', 'is_ration_semi_complete',
        'ecart_variation_reserve', 'strategie', 'lait_objectif_auge', 'race',
        'mois_lactation', 'mois_gestation', 'categorie_animal',
        // Champs multi-espèces (INRA 2018)
        'gmq', 'stade_physiologique', 'jours_gestation', 'jours_lactation',
        'nombre_jeunes', 'poids_portee', 'gmq_portee', 'mfc', 'mpc', 'type_production_ovin',
    ];

    protected function casts(): array
    {
        return [
            'is_ration_semi_complete' => 'boolean',
            'mois_lactation' => 'float',
            'mois_gestation' => 'float',
            'gmq' => 'integer',
            'jours_gestation' => 'integer',
            'jours_lactation' => 'integer',
            'nombre_jeunes' => 'integer',
            'gmq_portee' => 'integer',
            'poids_portee' => 'float',
            'mfc' => 'float',
            'mpc' => 'float',
        ];
    }

    /**
     * Catégorie d'animal résolue en enum (source de vérité du routage INRA 2018).
     */
    public function categorie(): CategorieAnimal
    {
        return CategorieAnimal::fromLoose($this->categorie_animal);
    }

    /**
     * @return BelongsTo<PlanRationnement, $this>
     */
    public function planRationnement(): BelongsTo
    {
        return $this->belongsTo(PlanRationnement::class);
    }

    /**
     * @return HasMany<RationAliment, $this>
     */
    public function rationAliments(): HasMany
    {
        return $this->hasMany(RationAliment::class)->orderBy('ordre');
    }

    /**
     * @return HasMany<Melange, $this>
     */
    public function melanges(): HasMany
    {
        return $this->hasMany(Melange::class)->orderBy('ordre');
    }
}
