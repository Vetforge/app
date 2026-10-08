<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\TypeAliment;
use App\Models\Aliment;
use App\Models\MelangeAliment;
use App\Models\RationAliment;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

/**
 * Reprend les règles et la normalisation (notamment du type d'aliment) de {@see StoreAlimentRequest}.
 */
class UpdateAlimentRequest extends StoreAlimentRequest
{
    protected function prepareForValidation(): void
    {
        parent::prepareForValidation();

        if ($this->input('type') === TypeAliment::Mineral->value && ! $this->exists('procede_technologique')) {
            $this->merge(['procede_technologique' => 'mineral']);
        }
    }

    public function rules(): array
    {
        $rules = parent::rules();
        if ($this->updatedAliment()->code_inra !== null) {
            $rules['breeder_id'] = ['prohibited'];
        }

        $rules['famille_botanique'] = ['sometimes', 'required', Rule::in(Aliment::FAMILLES_BOTANIQUES)];
        $rules['procede_technologique'] = ['sometimes', 'required', Rule::in(Aliment::PROCEDES_TECHNOLOGIQUES)];

        return $rules;
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $aliment = $this->updatedAliment();
                $data = array_replace($aliment->getAttributes(), $this->all());
                $nutritionChanged = false;
                foreach (self::sharedRules() as $field => $rules) {
                    if ($field === 'prix' || ! in_array('numeric', $rules, true) || ! $this->exists($field)) {
                        continue;
                    }
                    $incoming = $this->input($field);
                    $stored = $aliment->getAttribute($field);
                    if ($incoming === $stored || (is_numeric($incoming) && is_numeric($stored) && (float) $incoming === (float) $stored)) {
                        continue;
                    }
                    $nutritionChanged = true;
                    break;
                }

                self::appendNumericErrors($validator, $this->all());
                self::appendNutritionContractErrors($validator, $data, $nutritionChanged);
            },
            function (Validator $validator): void {
                if (TypeAliment::tryFromLoose($this->input('type')) === TypeAliment::Fourrage) {
                    return;
                }

                $aliment = $this->updatedAliment();
                if ($aliment->rationAliments()->where('is_volonte', true)->exists()
                    || $aliment->melangeAliments()->whereHas('melange', fn ($query) => $query->where('is_volonte', true))->exists()) {
                    $validator->errors()->add('type', 'Un aliment distribué à volonté doit rester un fourrage.');
                }
            },
        ];
    }

    protected function updatedAliment(): Aliment
    {
        $aliment = $this->route('aliment');
        if ($aliment instanceof Aliment) {
            return $aliment;
        }
        $component = $this->route('rationAliment') ?? $this->route('melangeAliment');
        if ($component instanceof RationAliment || $component instanceof MelangeAliment) {
            return $component->aliment;
        }

        abort(404);
    }
}
