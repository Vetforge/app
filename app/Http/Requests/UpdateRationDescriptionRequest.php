<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\CategorieAnimal;
use App\Services\Reference\BovinCroissanceReference;
use App\Support\PetitRuminantParameters;
use App\Support\RationScientificMatrix;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRationDescriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('categorie_animal') && $this->input('categorie_animal') !== null) {
            $resolved = CategorieAnimal::tryFromLoose((string) $this->input('categorie_animal'));

            // Un alias reconnu est normalisé vers sa valeur canonique ; une valeur inconnue est
            // laissée telle quelle pour être rejetée par Rule::enum (pas de conversion silencieuse).
            if ($resolved !== null) {
                $this->merge(['categorie_animal' => $resolved->value]);
            }
        }

        $categorie = CategorieAnimal::tryFromLoose((string) $this->input('categorie_animal'));
        if ($categorie === null) {
            return;
        }

        $petitRuminant = in_array($categorie, [
            CategorieAnimal::BrebisLaitiere,
            CategorieAnimal::BrebisAllaitante,
            CategorieAnimal::AgneauCroissance,
            CategorieAnimal::ChevreLaitiere,
            CategorieAnimal::ChevretteCroissance,
        ], true);
        $croissance = $categorie->estEnCroissance();
        $femelleAdulte = in_array($categorie, [
            CategorieAnimal::VacheLaitiere,
            CategorieAnimal::VacheAllaitante,
            CategorieAnimal::BrebisLaitiere,
            CategorieAnimal::BrebisAllaitante,
            CategorieAnimal::ChevreLaitiere,
        ], true);

        // Purger côté serveur les valeurs devenues invisibles après un changement de catégorie.
        // Cela empêche un ancien lait, GMQ, stade ou terme de réserves de contaminer le nouveau calcul.
        $clear = [];
        if ($petitRuminant) {
            $clear += ['mois_lactation' => null, 'mois_gestation' => null];
        } else {
            $clear += ['jours_lactation' => null, 'jours_gestation' => null];
        }
        if (! $croissance) {
            $clear += ['sexe' => null, 'age_jours' => null, 'reference_bovine' => null];
            if (! in_array($categorie, [CategorieAnimal::BrebisLaitiere, CategorieAnimal::BrebisAllaitante], true)) {
                $clear += ['gmq' => null, 'poids_adulte' => null];
            }
        }
        if ($croissance) {
            $clear += ['mois_lactation' => null, 'mois_gestation' => null, 'jours_lactation' => null];
            if ($categorie !== CategorieAnimal::ChevretteCroissance) {
                $clear += ['jours_gestation' => null];
            }
        }
        if (! $femelleAdulte) {
            $clear += [
                'lait_objectif' => null, 'lait_potentiel' => null, 'mfc' => null, 'mpc' => null,
                'gmq_portee' => null,
                'parite' => null, 'nec_velage' => null, 'ecart_variation_reserve' => null,
            ];
        }
        if (! $femelleAdulte && $categorie !== CategorieAnimal::ChevretteCroissance) {
            $clear += ['nombre_jeunes' => null, 'poids_portee' => null];
        }
        if (! in_array($categorie, [CategorieAnimal::VacheLaitiere, CategorieAnimal::ChevreLaitiere], true)) {
            $clear += ['age_mois' => null];
        }
        if (! in_array($categorie, [CategorieAnimal::BrebisLaitiere, CategorieAnimal::BrebisAllaitante], true)) {
            $clear += ['variation_nec' => null, 'duree_variation_nec_jours' => null];
        } else {
            $clear += ['ecart_variation_reserve' => null];
        }
        if ($petitRuminant && ! $croissance && (int) $this->input('jours_lactation', 0) === 0) {
            $clear += ['lait_objectif' => null, 'lait_potentiel' => null, 'gmq_portee' => null];
        }
        $clear['type_production_ovin'] = RationScientificMatrix::ovineProduction($categorie);
        $clear['stade_physiologique'] = RationScientificMatrix::physiologicalStage(
            $categorie,
            array_merge($this->all(), $clear),
        );
        $this->merge($clear);
    }

    public function rules(): array
    {
        $rules = [
            'nom' => ['required', 'string', 'max:255'],
            'categorie_animal' => ['required', Rule::enum(CategorieAnimal::class)],
            'effectif' => ['nullable', 'integer', 'min:1'],
            'lait_potentiel305j' => ['nullable', 'numeric', 'min:0'],
            'poids_vif' => ['required', 'numeric', 'gt:0'],
            'pourcentage_primipare' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'nec' => ['nullable', 'numeric', 'min:0', 'max:5'],
            'tb_annuel' => ['nullable', 'numeric', 'min:0'],
            'tp_annuel' => ['nullable', 'numeric', 'min:0'],
            'activite' => ['nullable', 'string', 'max:50'],
            'temperature_ambiante' => ['nullable', 'numeric'],
            'nec_velage' => ['nullable', 'numeric', 'min:0', 'max:5'],
            'ivv' => ['nullable', 'integer', 'min:0'],
            'poids_veau_naissance' => ['nullable', 'numeric', 'min:0'],
            'age_mois' => ['nullable', 'numeric', 'min:1', 'max:240'],
            'variation_nec' => ['nullable', 'numeric', 'min:-2', 'max:2'],
            'duree_variation_nec_jours' => ['nullable', 'integer', 'min:1', 'max:730'],
            'age_velage' => ['nullable', 'integer', 'min:0'],
            'age_jours' => ['nullable', 'integer', 'min:1', 'max:5000'],
            'sexe' => ['nullable', Rule::in(['femelle', 'male', 'male_castre'])],
            'parite' => ['nullable', 'integer', 'min:0', 'max:15'],
            'poids_adulte' => ['nullable', 'numeric', 'gt:0', 'max:1500'],
            'reference_bovine' => ['nullable', 'integer', 'min:1', 'max:14'],
            'lait_potentiel' => ['nullable', 'numeric', 'min:0'],
            'lait_objectif305j' => ['nullable', 'numeric', 'min:0'],
            'stade_moyen' => ['nullable', 'integer', 'min:0'],
            'lait_objectif' => ['nullable', 'numeric', 'min:0'],
            'is_ration_semi_complete' => ['boolean'],
            'ecart_variation_reserve' => ['nullable', 'numeric'],
            'strategie' => ['nullable', 'string', 'max:100'],
            'lait_objectif_auge' => ['nullable', 'numeric', 'min:0'],
            'race' => ['required', 'string', 'max:100'],
            'mois_lactation' => ['nullable', 'numeric', 'min:0', 'max:12'],
            'mois_gestation' => ['nullable', 'numeric', 'min:0', 'max:9'],

            // Champs multi-espèces (INRA 2018).
            'gmq' => ['nullable', 'integer', 'min:0', 'max:3000'],
            'stade_physiologique' => ['nullable', Rule::in(['entretien', 'gestation', 'lactation', 'croissance', 'engraissement', 'tarie', 'allaitement', 'traite'])],
            'jours_gestation' => ['nullable', 'integer', 'min:0', 'max:290'],
            'jours_lactation' => ['nullable', 'integer', 'min:0', 'max:365'],
            'nombre_jeunes' => ['nullable', 'integer', 'min:1', 'max:6'],
            'poids_portee' => ['nullable', 'numeric', 'gt:0'],
            'gmq_portee' => ['nullable', 'integer', 'min:0', 'max:3000'],
            'mfc' => ['nullable', 'numeric', 'min:0'],
            'mpc' => ['nullable', 'numeric', 'min:0'],
            'type_production_ovin' => ['nullable', Rule::in(['lait', 'viande'])],
        ];

        $categorie = CategorieAnimal::tryFromLoose((string) $this->input('categorie_animal'));
        if ($categorie === CategorieAnimal::VacheLaitiere) {
            $rules['age_mois'][] = 'min:24';
        }
        if ($categorie !== null) {
            foreach (RationScientificMatrix::for($categorie)['champs_interdits'] as $field) {
                if (isset($rules[$field])) {
                    $rules[$field][] = Rule::prohibitedIf(true);
                }
            }
        }

        return $rules;
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $categorie = CategorieAnimal::tryFrom((string) $this->input('categorie_animal'));
                $inra = $this->route('plan')?->inra;

                if ($categorie !== null
                    && $inra !== '2018'
                    && ! in_array($categorie, [CategorieAnimal::VacheLaitiere, CategorieAnimal::VacheAllaitante], true)
                ) {
                    $validator->errors()->add(
                        'categorie_animal',
                        'Cette catégorie n\'est disponible que dans un plan au référentiel INRA 2018.'
                    );
                }

                if ($categorie === null) {
                    return;
                }

                $required = function (string $field, string $message) use ($validator): void {
                    $value = $this->input($field);
                    if ($value === null || $value === '') {
                        $validator->errors()->add($field, $message);
                    }
                };

                foreach (RationScientificMatrix::for($categorie)['champs_requis'] as $field) {
                    $required($field, "Le champ {$field} est requis par le modèle scientifique sélectionné.");
                }

                foreach (PetitRuminantParameters::errors($categorie, $this->all()) as $field => $message) {
                    if (! $validator->errors()->has($field)) {
                        $validator->errors()->add($field, $message);
                    }
                }

                if ($categorie->estEnCroissance()) {
                    $required('gmq', 'Le GMQ est requis pour un animal en croissance ou à l\'engraissement.');
                }
                if (in_array($categorie, [CategorieAnimal::BovinCroissance, CategorieAnimal::BovinEngraissement], true)) {
                    foreach (BovinCroissanceReference::parameterErrors(
                        $categorie,
                        $this->filled('reference_bovine') ? (int) $this->input('reference_bovine') : null,
                        $this->filled('poids_vif') ? (float) $this->input('poids_vif') : null,
                        $this->filled('gmq') ? (int) $this->input('gmq') : null,
                    ) as $field => $message) {
                        $validator->errors()->add($field, $message);
                    }
                }

                if (in_array($categorie, [
                    CategorieAnimal::VacheAllaitante,
                    CategorieAnimal::BrebisLaitiere,
                    CategorieAnimal::BrebisAllaitante,
                    CategorieAnimal::ChevreLaitiere,
                ], true)) {
                    $required('parite', 'La parité est requise pour cette femelle reproductrice.');
                }

            },
        ];
    }
}
