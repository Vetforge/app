<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\CategorieAnimal;

final class RationScientificMatrix
{
    public const VERSION = 'inra-2018-ration-v1.1';

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
                ['BOV-L03 : croissance du lot maintenue selon le pourcentage de primipares.'],
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
                'ovin_lait', ['parite', 'jours_lactation', 'lait_objectif'], $adultForbidden,
                ['Lactation limitée aux 20 premières semaines.'],
            ],
            CategorieAnimal::BrebisAllaitante => [
                'ovin_allaitant', ['parite', 'jours_lactation', 'nombre_jeunes', 'gmq_portee'], $adultForbidden,
                ['Production calculée à partir du GMQ de portée.'],
            ],
            CategorieAnimal::AgneauCroissance => [
                'ovin_agneau_engraissement', ['gmq', 'age_jours', 'sexe', 'poids_adulte'], $growthForbidden,
                ['Modèle réservé à l’agneau à l’engraissement ; pas à l’agnelle de renouvellement.'],
            ],
            CategorieAnimal::ChevreLaitiere => [
                'caprin_lait', ['race', 'parite', 'jours_lactation', 'lait_objectif'], $adultForbidden,
                ['Races Alpine et Saanen ; « Autre » est calculée comme Alpine (race de référence).'],
            ],
            CategorieAnimal::ChevretteCroissance => [
                'caprin_chevrette_croissance', ['gmq', 'age_jours', 'sexe', 'poids_adulte', 'race'], $growthForbidden,
                ['Femelles uniquement ; races Alpine et Saanen, « Autre » calculée comme Alpine.'],
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
