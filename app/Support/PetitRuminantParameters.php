<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\CategorieAnimal;
use App\Enums\Espece;

final class PetitRuminantParameters
{
    /** @param array<string, mixed> $data
     * @return array<string, string>
     */
    public static function errors(CategorieAnimal $categorie, array $data): array
    {
        if (! in_array($categorie->espece(), [Espece::Ovin, Espece::Caprin], true)) {
            return [];
        }

        $errors = [];
        $labels = [
            'poids_vif' => 'poids vif', 'race' => 'race', 'parite' => 'parité',
            'jours_lactation' => 'nombre de jours de lactation (0 pour un animal tari)',
            'lait_objectif' => 'production laitière', 'gmq' => 'GMQ', 'age_jours' => 'âge en jours',
            'sexe' => 'sexe', 'poids_adulte' => 'poids adulte cible', 'nombre_jeunes' => 'nombre de jeunes',
            'poids_portee' => 'poids attendu de la portée à la naissance', 'gmq_portee' => 'GMQ de la portée entre 10 et 30 jours après l’agnelage',
        ];
        $required = function (string $field) use (&$errors, $data, $labels): void {
            if (! isset($data[$field]) || $data[$field] === '') {
                $errors[$field] = 'Champ requis : '.$labels[$field].'.';
            }
        };
        foreach (['poids_vif', 'race', ...RationScientificMatrix::for($categorie)['champs_requis']] as $field) {
            $required($field);
        }
        $weight = (float) ($data['poids_vif'] ?? 0);
        if ($weight <= 0) {
            $errors['poids_vif'] = 'Saisissez un poids vif supérieur à zéro.';
        }
        $lactation = (int) ($data['jours_lactation'] ?? 0);
        $gestation = (int) ($data['jours_gestation'] ?? 0);
        $maxGestation = $categorie->espece() === Espece::Ovin ? 147 : 150;
        $maxLactation = $categorie->espece() === Espece::Ovin ? 140 : 365;
        if ($gestation < 0 || $gestation > $maxGestation) {
            $errors['jours_gestation'] = "La gestation est limitée à {$maxGestation} jours dans ce modèle.";
        }
        if ($lactation < 0 || $lactation > $maxLactation) {
            $errors['jours_lactation'] = "La lactation est limitée à {$maxLactation} jours dans ce modèle.";
        }
        if ($gestation > 0) {
            $required('nombre_jeunes');
            $required('poids_portee');
        }
        if ($categorie === CategorieAnimal::ChevretteCroissance && $gestation > 0 && (int) ($data['nombre_jeunes'] ?? 0) > 2) {
            $errors['nombre_jeunes'] = 'Les minéraux de gestation des chevrettes sont documentés pour un ou deux chevreaux.';
        }
        if (in_array($categorie, [CategorieAnimal::BrebisLaitiere, CategorieAnimal::BrebisAllaitante], true)) {
            $delta = (float) ($data['variation_nec'] ?? 0);
            $duration = (int) ($data['duree_variation_nec_jours'] ?? 0);
            if ($delta != 0 && $duration <= 0) {
                $errors['duree_variation_nec_jours'] = 'Indiquez la durée de variation des réserves en jours.';
            }
            if ($delta < 0 && ($lactation < 1 || $lactation > 42)) {
                $errors['variation_nec'] = 'La mobilisation est modélisée pendant les six premières semaines de lactation.';
            }
            if (isset($data['nec']) && ((float) $data['nec'] + $delta < 0 || (float) $data['nec'] + $delta > 5)) {
                $errors['variation_nec'] = 'La NEC visée doit rester entre 0 et 5.';
            }
            if ((int) ($data['gmq'] ?? 0) > 0) {
                if (! isset($data['poids_adulte']) || (float) $data['poids_adulte'] < $weight) {
                    $errors['poids_adulte'] = 'Indiquez un poids adulte cible supérieur ou égal au poids actuel.';
                }
                if ($gestation < 1 || $gestation > 100) {
                    $errors['gmq'] = 'La croissance de la jeune reproductrice est modélisée pendant les 100 premiers jours de gestation.';
                }
            }
        }
        if ($lactation > 0 && $categorie === CategorieAnimal::BrebisAllaitante) {
            $required('nombre_jeunes');
            $required('gmq_portee');
        }
        if ($lactation > 0 && $categorie->estLaitiere()) {
            $required('lait_objectif');
        }
        if ($categorie->estEnCroissance()) {
            if ((int) ($data['age_jours'] ?? 0) <= 0) {
                $errors['age_jours'] = 'Saisissez l’âge réel en jours.';
            }
            if (isset($data['gmq']) && ((int) $data['gmq'] < 0 || (int) $data['gmq'] > 3000)) {
                $errors['gmq'] = 'Saisissez un GMQ entre 0 et 3 000 g/j.';
            }
            if (isset($data['poids_adulte']) && (float) $data['poids_adulte'] < $weight) {
                $errors['poids_adulte'] = 'Le poids adulte cible doit être au moins égal au poids vif actuel.';
            }
            if (! in_array($data['sexe'] ?? null, ['femelle', 'male', 'male_castre'], true)) {
                $errors['sexe'] = 'Sélectionnez le sexe de l’animal.';
            }
        }
        if ($categorie === CategorieAnimal::ChevretteCroissance) {
            if (($data['sexe'] ?? null) !== 'femelle') {
                $errors['sexe'] = 'Ce modèle concerne les chevrettes femelles.';
            }
            if ((int) ($data['age_jours'] ?? 0) > 730) {
                $errors['age_jours'] = 'Les courbes de croissance des chevrettes sont établies jusqu’à deux ans (730 jours).';
            }
        }
        if ($categorie->espece() === Espece::Caprin && ! in_array($data['race'] ?? null, ['alpine', 'saanen', 'autre'], true)) {
            $errors['race'] = 'Sélectionnez Alpine, Saanen ou Autre (calculée comme Alpine).';
        }

        return $errors;
    }
}
