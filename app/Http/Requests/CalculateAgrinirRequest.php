<?php

namespace App\Http\Requests;

use App\Services\Agrinir\ForageCalculator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CalculateAgrinirRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string'],
            'inra' => ['required', 'in:2007,2018'],
            'aliment_de_reference_id' => [
                'nullable',
                'integer',
                Rule::exists('aliments', 'id')->whereNotNull('code_inra'),
            ],
            'params' => ['required', 'array'],
            'params.humidite' => ['required', 'numeric', 'min:0', 'max:99'],
            'params.proteine' => ['required', 'numeric', 'min:0'],
            'params.ndf' => ['required', 'numeric', 'min:0'],
            'params.adf' => ['required', 'numeric', 'min:0'],
            'params.cendres' => ['required', 'numeric', 'min:0'],
            'params.matiere_grasse' => ['required', 'numeric', 'min:0'],
            'params.amidon' => [
                Rule::requiredIf(fn (): bool => ForageCalculator::requiresAmidon((string) $this->input('type'))),
                'nullable',
                'numeric',
                'min:0',
            ],
            'params.ca' => ['nullable', 'numeric', 'min:0'],
            'params.p' => ['nullable', 'numeric', 'min:0'],
            'params.mg' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'type.required' => 'Le type de fourrage est obligatoire.',
            'inra.required' => 'Le référentiel INRA est obligatoire.',
            'inra.in' => 'Le référentiel INRA sélectionné est invalide.',
            'aliment_de_reference_id.exists' => "L'aliment de référence sélectionné est introuvable.",
            'params.humidite.required' => "L'humidité est obligatoire.",
            'params.humidite.max' => "L'humidité doit rester inférieure à 99 %.",
            'params.proteine.required' => 'La teneur en protéines est obligatoire.',
            'params.ndf.required' => 'La valeur NDF est obligatoire.',
            'params.adf.required' => 'La valeur ADF est obligatoire.',
            'params.cendres.required' => 'La teneur en cendres est obligatoire.',
            'params.matiere_grasse.required' => 'La matière grasse est obligatoire.',
            'params.amidon.required' => "L'amidon est obligatoire pour ce type de fourrage.",
        ];
    }
}
