<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\CategorieAnimal;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRationRequest extends FormRequest
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
    }

    public function rules(): array
    {
        return [
            'nom' => ['required', 'string', 'max:255'],
            'categorie_animal' => ['required', Rule::enum(CategorieAnimal::class)],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $categorie = CategorieAnimal::tryFrom((string) $this->input('categorie_animal'));
                $inra = $this->route('plan')?->inra;

                // Le moteur INRA 2007 est limité aux vaches ; toute autre catégorie exige le référentiel 2018.
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
