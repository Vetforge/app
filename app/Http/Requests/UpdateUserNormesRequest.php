<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\RationNormes;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateUserNormesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'normes' => ['required', 'array'],
        ];

        foreach (RationNormes::definitions() as $key => $definition) {
            $rules["normes.$key"] = ['required', 'array'];
            $rules["normes.$key.min"] = ['nullable', 'numeric'];
            $rules["normes.$key.max"] = ['nullable', 'numeric'];

            if ($definition['default_min'] !== null && $definition['default_max'] !== null) {
                $rules["normes.$key.max"][] = "gte:normes.$key.min";
            }
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'normes.required' => 'Les normes à enregistrer sont manquantes.',
            'normes.array' => 'Le format des normes est invalide.',
            'normes.*.array' => 'Le format d’une norme est invalide.',
            'normes.*.min.numeric' => 'La borne basse doit être un nombre.',
            'normes.*.max.numeric' => 'La borne haute doit être un nombre.',
            'normes.*.max.gte' => 'La borne haute doit être supérieure ou égale à la borne basse.',
        ];
    }
}
