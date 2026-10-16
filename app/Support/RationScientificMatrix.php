<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\CategorieAnimal;

final class RationScientificMatrix
{
    public const VERSION = 'inra-2018-ration-v1.2';

    /**
     * @return array{version: string, modele: string, niveau: string, champs_requis: list<string>, champs_interdits: list<string>, domaines: array<string, string>, limites: list<string>}
     */
    public static function for(CategorieAnimal $categorie): array
    {
        $growthForbidden = [
            'lait_objectif', 'lait_potentiel', 'mois_lactation', 'mois_gestation',
            'jours_lactation', 'jours_gestation', 'nombre_jeunes', 'poids_portee',
            'gmq_portee', 'parite', 'nec_velage', 'ecart_variation_reserve',
        ];
        $adultForbidden = ['gmq', 'age_jours', 'sexe', 'poids_adulte', 'reference_bovine'];

        [$modele, $required, $forbidden, $limites] = match ($categorie) {
            CategorieAnimal::VacheLaitiere => [
                'bovin_lait', ['lait_objectif', 'mois_lactation'], $adultForbidden,
                ['Croissance : équations 17.10/17.18/17.21/17.22, âge en mois. Sans âge saisi : primipares de 36 mois ; un lot est pondéré par leur pourcentage.'],
            ],
            CategorieAnimal::VacheAllaitante => [
                'bovin_allaitant', ['parite'], $adultForbidden,
                ['Le lait correspond au lait tété par le veau.'],
            ],
            CategorieAnimal::BovinCroissance => [
                'bovin_croissance_ufl', ['gmq', 'reference_bovine'], $growthForbidden,
                ['Références INRA 10 à 14 ; GMQ inférieur ou égal à 1 000 g/j.'],
            ],
            CategorieAnimal::BovinEngraissement => [
                'bovin_engraissement_ufv', ['gmq', 'reference_bovine'], $growthForbidden,
                ['Références INRA 1 à 9 ; GMQ supérieur à 1 000 g/j.'],
            ],
            CategorieAnimal::BrebisLaitiere => [
                'ovin_lait', ['parite', 'jours_lactation'], array_values(array_diff($adultForbidden, ['gmq', 'poids_adulte'])),
                ['Lactation limitée aux 20 premières semaines ; lait en litres/j et TB/TP en g/l. Croissance des jeunes reproductrices : 100 premiers jours de gestation, coefficients Lacaune (approximation pour les autres races).'],
            ],
            CategorieAnimal::BrebisAllaitante => [
                'ovin_allaitant', ['parite', 'jours_lactation'], array_values(array_diff($adultForbidden, ['gmq', 'poids_adulte'])),
                ['Production calculée à partir du GMQ de portée. Croissance des jeunes reproductrices : 100 premiers jours de gestation, coefficients Lacaune (approximation pour les autres races).'],
            ],
            CategorieAnimal::AgneauCroissance => [
                'ovin_agneau_engraissement', ['gmq', 'age_jours', 'sexe', 'poids_adulte'], $growthForbidden,
                ['Modèle réservé à l’agneau à l’engraissement ; pas à l’agnelle de renouvellement.'],
            ],
            CategorieAnimal::ChevreLaitiere => [
                'caprin_lait', ['race', 'parite', 'jours_lactation'], $adultForbidden,
                ['Races Alpine et Saanen ; « Autre » est calculée comme Alpine (race de référence).'],
            ],
            CategorieAnimal::ChevretteCroissance => [
                'caprin_chevrette_croissance', ['gmq', 'age_jours', 'sexe', 'poids_adulte', 'race'], array_values(array_diff($growthForbidden, ['jours_gestation', 'nombre_jeunes', 'poids_portee'])),
                ['Femelles uniquement ; races Alpine et Saanen, « Autre » calculée comme Alpine. Gestation simple ou double ajoutée au compartiment croissance ; ingestion approchée par 21.50.'],
            ],
        };

        return [
            'version' => self::VERSION,
            'modele' => $modele,
            'niveau' => 'domaine_encadre',
            'champs_requis' => $required,
            'champs_interdits' => $forbidden,
            'domaines' => [
                'energie' => 'implemente',
                'proteines' => 'implemente',
                'mineraux' => 'implemente',
                'vitamines' => 'supplementation_distincte',
            ],
            'limites' => $limites,
            'reference' => 'INRA Feeding System for Ruminants, 2e édition anglaise (2019), système INRA 2018, chapitres 8 et 17–21.',
            'unites' => ['lait' => $categorie->espece()->value === 'ovin' ? 'l/j' : 'kg/j', 'gmq' => 'g/j', 'mineraux' => 'g/j'],
        ];
    }

    public static function isAvailable(CategorieAnimal $categorie): bool
    {
        return self::for($categorie)['modele'] !== '';
    }

    public static function ovineProduction(CategorieAnimal $categorie): ?string
    {
        return match ($categorie) {
            CategorieAnimal::BrebisLaitiere => 'lait',
            CategorieAnimal::BrebisAllaitante,
            CategorieAnimal::AgneauCroissance => 'viande',
            default => null,
        };
    }

    /** @param array<string, mixed> $data */
    public static function physiologicalStage(CategorieAnimal $categorie, array $data): string
    {
        if ($categorie === CategorieAnimal::BovinEngraissement || $categorie === CategorieAnimal::AgneauCroissance) {
            return 'engraissement';
        }
        if ($categorie->estEnCroissance()) {
            return 'croissance';
        }

        $lactation = $categorie->espece()->value === 'bovin'
            ? (float) ($data['mois_lactation'] ?? 0)
            : (float) ($data['jours_lactation'] ?? 0);
        if ($lactation > 0) {
            return match ($categorie) {
                CategorieAnimal::BrebisAllaitante,
                CategorieAnimal::VacheAllaitante => 'allaitement',
                CategorieAnimal::BrebisLaitiere => 'traite',
                default => 'lactation',
            };
        }

        $gestation = $categorie->espece()->value === 'bovin'
            ? (float) ($data['mois_gestation'] ?? 0)
            : (float) ($data['jours_gestation'] ?? 0);

        return $gestation > 0 ? 'gestation' : 'tarie';
    }
}
