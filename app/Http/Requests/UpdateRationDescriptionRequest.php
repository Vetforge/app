<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateRationDescriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nom' => ['required', 'string', 'max:255'],
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
            'categorie_animal' => ['nullable', 'string', 'max:100'],
        ];
    }
}
