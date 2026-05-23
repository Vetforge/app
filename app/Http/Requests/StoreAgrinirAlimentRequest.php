<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Services\Agrinir\ForageCalculator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAgrinirAlimentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'nom' => ['required', 'string', 'max:255'],
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
            'valeurs' => ['required', 'array'],
            'valeurs.ms' => ['nullable', 'numeric'],
            'valeurs.mat' => ['nullable', 'numeric'],
            'valeurs.ndf' => ['nullable', 'numeric'],
            'valeurs.adf' => ['nullable', 'numeric'],
            'valeurs.mo' => ['nullable', 'numeric'],
            'valeurs.cb' => ['nullable', 'numeric'],
            'valeurs.eb' => ['nullable', 'numeric'],
            'valeurs.em' => ['nullable', 'numeric'],
            'valeurs.de' => ['nullable', 'numeric'],
            'valeurs.dmo' => ['nullable', 'numeric'],
            'valeurs.niref' => ['nullable', 'numeric'],
            'valeurs.dt_n' => ['nullable', 'numeric'],
            'valeurs.dr_n' => ['nullable', 'numeric'],
            'valeurs.ufl' => ['nullable', 'numeric'],
            'valeurs.ufv' => ['nullable', 'numeric'],
            'valeurs.pdia' => ['nullable', 'numeric'],
            'valeurs.pdi' => ['nullable', 'numeric'],
            'valeurs.bpr' => ['nullable', 'numeric'],
            'valeurs.uem' => ['nullable', 'numeric'],
            'valeurs.uel' => ['nullable', 'numeric'],
            'valeurs.ueb' => ['nullable', 'numeric'],
            'valeurs.ca' => ['nullable', 'numeric'],
            'valeurs.caabs' => ['nullable', 'numeric'],
            'valeurs.p' => ['nullable', 'numeric'],
            'valeurs.pabs' => ['nullable', 'numeric'],
            'valeurs.mg' => ['nullable', 'numeric'],
        ];
    }

    public function messages(): array
    {
        return [
            'nom.required' => 'Le nom du nouvel aliment est obligatoire.',
            'type.required' => 'Le type AgriNIR est obligatoire.',
            'inra.required' => 'Le référentiel INRA est obligatoire.',
            'aliment_de_reference_id.exists' => "L'aliment de référence sélectionné est introuvable.",
            'params.required' => "Les paramètres d'analyse sont obligatoires.",
            'params.amidon.required' => "L'amidon est obligatoire pour ce type de fourrage.",
            'valeurs.required' => 'Les valeurs calculées sont obligatoires avant sauvegarde.',
        ];
    }
}
