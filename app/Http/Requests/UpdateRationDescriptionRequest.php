<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\CategorieAnimal;
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
            $this->merge([
                'categorie_animal' => CategorieAnimal::fromLoose((string) $this->input('categorie_animal'))->value,
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'nom' => ['required', 'string', 'max:255'],
            'categorie_animal' => ['required', Rule::enum(CategorieAnimal::class)],
            'effectif' => ['nullable', 'integer', 'min:1'],
            'lait_potentiel305j' => ['nullable', 'numeric', 'min:0'],
            'poids_vif' => ['nullable', 'numeric', 'min:0'],
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
            'lait_objectif305j' => ['nullable', 'numeric', 'min:0'],
            'stade_moyen' => ['nullable', 'integer', 'min:0'],
            'lait_objectif' => ['nullable', 'numeric', 'min:0'],
            'is_ration_semi_complete' => ['boolean'],
            'ecart_variation_reserve' => ['nullable', 'numeric'],
            'strategie' => ['nullable', 'string', 'max:100'],
            'lait_objectif_auge' => ['nullable', 'numeric', 'min:0'],
            'race' => ['nullable', 'string', 'max:100'],
            'mois_lactation' => ['nullable', 'numeric', 'min:0', 'max:12'],
            'mois_gestation' => ['nullable', 'numeric', 'min:0', 'max:9'],

            // Champs multi-espèces (INRA 2018).
            'gmq' => ['nullable', 'integer', 'min:0', 'max:3000'],
            'stade_physiologique' => ['nullable', 'string', 'max:50'],
            'jours_gestation' => ['nullable', 'integer', 'min:0', 'max:290'],
            'jours_lactation' => ['nullable', 'integer', 'min:0', 'max:400'],
            'nombre_jeunes' => ['nullable', 'integer', 'min:0', 'max:6'],
            'poids_portee' => ['nullable', 'numeric', 'min:0'],
            'gmq_portee' => ['nullable', 'integer', 'min:0', 'max:3000'],
            'mfc' => ['nullable', 'numeric', 'min:0'],
            'mpc' => ['nullable', 'numeric', 'min:0'],
            'type_production_ovin' => ['nullable', Rule::in(['lait', 'viande'])],
        ];
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
            },
        ];
    }
}
