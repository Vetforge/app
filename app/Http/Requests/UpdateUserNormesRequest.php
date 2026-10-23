<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\CategorieAnimal;
use App\Support\RationNormes;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserNormesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $normes = $this->input('normes');
        if (! is_array($normes)) {
            return;
        }

        $category = $this->input('categorie_animal');
        $categorie = is_string($category) ? CategorieAnimal::tryFrom($category) : null;
        $active = RationNormes::active($this->user()?->normes_personnalisees, $categorie ?? CategorieAnimal::VacheLaitiere);
        foreach ($normes as $key => $bounds) {
            if (is_array($bounds) && isset($active[$key])) {
                $normes[$key] = array_replace($active[$key], $bounds);
            }
        }

        $this->merge(['normes' => $normes]);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'categorie_animal' => ['sometimes', 'required', Rule::enum(CategorieAnimal::class)],
            'normes' => ['required', 'array:'.implode(',', array_keys(RationNormes::definitions()))],
        ];

        foreach (RationNormes::editable() as $definition) {
            $key = $definition['key'];
            $rules["normes.$key"] = ['sometimes', 'array:min,max'];
            $rules["normes.$key.min"] = ['nullable', 'numeric'];
            $rules["normes.$key.max"] = ['nullable', 'numeric'];

            if ($definition['supports_min'] && $definition['supports_max']
                && is_numeric($this->input("normes.$key.min")) && is_numeric($this->input("normes.$key.max"))) {
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
            'categorie_animal.enum' => 'Sélectionnez une catégorie animale valide.',
            'categorie_animal.required' => 'Sélectionnez une catégorie animale.',
            'normes.required' => 'Les normes à enregistrer sont manquantes.',
            'normes.array' => 'Le format des normes est invalide.',
            'normes.*.array' => 'Le format d’une norme est invalide.',
            'normes.*.min.numeric' => 'La borne basse doit être un nombre.',
            'normes.*.max.numeric' => 'La borne haute doit être un nombre.',
            'normes.*.max.gte' => 'La borne haute doit être supérieure ou égale à la borne basse.',
        ];
    }
}
