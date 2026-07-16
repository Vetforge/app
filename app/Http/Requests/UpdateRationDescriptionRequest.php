<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\CategorieAnimal;
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
            $clear += ['gmq' => null, 'sexe' => null, 'age_jours' => null, 'poids_adulte' => null, 'reference_bovine' => null];
        }
        if (! $femelleAdulte) {
            $clear += [
                'lait_objectif' => null, 'lait_potentiel' => null, 'mfc' => null, 'mpc' => null,
                'nombre_jeunes' => null, 'poids_portee' => null, 'gmq_portee' => null,
                'parite' => null, 'nec_velage' => null, 'ecart_variation_reserve' => null,
            ];
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

                if ($categorie->estEnCroissance()) {
                    $required('gmq', 'Le GMQ est requis pour un animal en croissance ou à l\'engraissement.');
                }
                if (in_array($categorie, [CategorieAnimal::BovinCroissance, CategorieAnimal::BovinEngraissement], true)) {
                    $required('reference_bovine', 'La référence bovine INRA (Table 19.2, catégories 1 à 14) est requise.');
                    $reference = (int) $this->input('reference_bovine', 0);
                    $valides = $categorie === CategorieAnimal::BovinEngraissement ? range(1, 9) : range(10, 14);
                    if ($reference > 0 && ! in_array($reference, $valides, true)) {
                        $validator->errors()->add('reference_bovine', 'La référence choisie ne correspond pas au système énergétique de cette catégorie.');
                    }
                }

                if ($categorie === CategorieAnimal::BovinCroissance && (float) $this->input('gmq', 0) > 1000) {
                    $validator->errors()->add('gmq', 'Au-delà de 1 000 g/j, utiliser la catégorie bovin à l\'engraissement (UFV).');
                }
                if ($categorie === CategorieAnimal::BovinEngraissement && (float) $this->input('gmq', 0) <= 1000) {
                    $validator->errors()->add('gmq', 'La catégorie engraissement (UFV) exige un GMQ supérieur à 1 000 g/j.');
                }

                if (in_array($categorie, [CategorieAnimal::AgneauCroissance, CategorieAnimal::ChevretteCroissance], true)) {
                    $required('age_jours', 'L\'âge réel (jours) est requis pour sélectionner les équations de croissance.');
                    $required('sexe', 'Le sexe est requis pour un jeune animal.');
                    $required('poids_adulte', 'Le poids adulte cible est requis pour les équations de maturité et de minéraux.');
                }
                if ($categorie === CategorieAnimal::ChevretteCroissance && $this->input('sexe') !== 'femelle') {
                    $validator->errors()->add('sexe', 'Les équations 21.39 à 21.43 de cette catégorie concernent les chevrettes femelles ; le jeune mâle n\'est pas proposé sous un modèle inadéquat.');
                }
                if (in_array($categorie, [CategorieAnimal::ChevreLaitiere, CategorieAnimal::ChevretteCroissance], true)) {
                    $required('race', 'La race est requise pour le modèle caprin (Alpine, Saanen ou Autre).');
                    if (! in_array((string) $this->input('race'), ['alpine', 'saanen', 'autre'], true)) {
                        $validator->errors()->add('race', 'Le modèle caprin accepte les races Alpine, Saanen ou Autre (calculée comme Alpine).');
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

                $joursGestation = (int) $this->input('jours_gestation', 0);
                if ($categorie->espece()->value === 'ovin' && $joursGestation > 147) {
                    $validator->errors()->add('jours_gestation', 'La gestation ovine est limitée à 147 jours dans ce modèle.');
                }
                if ($categorie->espece()->value === 'caprin' && $joursGestation > 150) {
                    $validator->errors()->add('jours_gestation', 'La gestation caprine est limitée à 150 jours dans ce modèle.');
                }
                if ($categorie->espece()->value === 'ovin' && (int) $this->input('jours_lactation', 0) > 140) {
                    $validator->errors()->add('jours_lactation', 'Les équations ovines implémentées sont limitées aux 20 premières semaines (140 jours).');
                }

                if ($joursGestation > 0 && in_array($categorie, [
                    CategorieAnimal::BrebisLaitiere,
                    CategorieAnimal::BrebisAllaitante,
                    CategorieAnimal::ChevreLaitiere,
                ], true)) {
                    $required('nombre_jeunes', 'Le nombre de jeunes est requis pour une femelle gestante.');
                    $required('poids_portee', 'Le poids attendu de la portée est requis pour une femelle gestante.');
                }

                if (in_array($categorie, [CategorieAnimal::BrebisLaitiere, CategorieAnimal::ChevreLaitiere], true)
                    && (int) $this->input('jours_lactation', 0) > 0
                ) {
                    $required('lait_objectif', 'La production laitière est requise pendant la lactation.');
                }
            },
        ];
    }
}
