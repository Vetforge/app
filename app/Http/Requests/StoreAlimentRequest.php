<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Aliment;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAlimentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('type') && $this->input('type') !== null && $this->input('type') !== '') {
            // Normaliser vers le jeton canonique (« Concentré » → « Conc », « Minéral » → « Mineral »).
            $this->merge(['type' => Aliment::canonicalType($this->input('type'))]);
        }
    }

    public function rules(): array
    {
        return self::sharedRules();
    }

    /** @return array<string, array<int, mixed>> */
    public static function sharedRules(): array
    {
        return [
            'type' => ['required', Rule::in(Aliment::TYPES_CANONIQUES)],
            'famille_botanique' => ['nullable', Rule::in(Aliment::FAMILLES_BOTANIQUES)],
            'procede_technologique' => ['nullable', Rule::in(Aliment::PROCEDES_TECHNOLOGIQUES)],
            'libelle0' => ['required', 'string', 'max:255'],
            'libelle1' => ['nullable', 'string', 'max:255'],
            'libelle2' => ['nullable', 'string', 'max:255'],
            'libelle3' => ['nullable', 'string', 'max:255'],
            'libelle4' => ['nullable', 'string', 'max:255'],
            'prix' => ['nullable', 'numeric', 'min:0'],
            'usage_aliment' => ['nullable', 'string', 'max:255'],
            'ms' => ['nullable', 'numeric'],
            'mo' => ['nullable', 'numeric'],
            'mat' => ['nullable', 'numeric'],
            'cb' => ['nullable', 'numeric'],
            'ndf' => ['nullable', 'numeric'],
            'adf' => ['nullable', 'numeric'],
            'adl' => ['nullable', 'numeric'],
            'ee' => ['nullable', 'numeric'],
            'ag' => ['nullable', 'numeric'],
            'eb' => ['nullable', 'numeric'],
            'em' => ['nullable', 'numeric'],
            'amidon' => ['nullable', 'numeric'],
            'sucres' => ['nullable', 'numeric'],
            'pf' => ['nullable', 'numeric'],
            'd_mo' => ['nullable', 'numeric'],
            'd_ma' => ['nullable', 'numeric'],
            'd_cb' => ['nullable', 'numeric'],
            'd_ndf' => ['nullable', 'numeric'],
            'd_adf' => ['nullable', 'numeric'],
            'd_e' => ['nullable', 'numeric'],
            'dt_n' => ['nullable', 'numeric'],
            'dt6_n' => ['nullable', 'numeric'],
            'dr_n' => ['nullable', 'numeric'],
            'dt_ami' => ['nullable', 'numeric'],
            'dt6_ami' => ['nullable', 'numeric'],
            'dt_ms' => ['nullable', 'numeric'],
            'dt6_ms' => ['nullable', 'numeric'],
            'ufl' => ['nullable', 'numeric'],
            'ufv' => ['nullable', 'numeric'],
            'uem' => ['nullable', 'numeric'],
            'uel' => ['nullable', 'numeric'],
            'ueb' => ['nullable', 'numeric'],
            'pdia' => ['nullable', 'numeric'],
            'pdi' => ['nullable', 'numeric'],
            'bpr' => ['nullable', 'numeric'],
            'niref' => ['nullable', 'numeric'],
            'lys_di' => ['nullable', 'numeric'],
            'met_di' => ['nullable', 'numeric'],
            'his_di' => ['nullable', 'numeric'],
            'arg_di' => ['nullable', 'numeric'],
            'thr_di' => ['nullable', 'numeric'],
            'val_di' => ['nullable', 'numeric'],
            'ile_di' => ['nullable', 'numeric'],
            'leu_di' => ['nullable', 'numeric'],
            'phe_di' => ['nullable', 'numeric'],
            'ca' => ['nullable', 'numeric'],
            'caabs' => ['nullable', 'numeric'],
            'p' => ['nullable', 'numeric'],
            'pabs' => ['nullable', 'numeric'],
            'mg' => ['nullable', 'numeric'],
            'na' => ['nullable', 'numeric'],
            'k' => ['nullable', 'numeric'],
            'cl' => ['nullable', 'numeric'],
            's' => ['nullable', 'numeric'],
            'be' => ['nullable', 'numeric'],
            'baca' => ['nullable', 'numeric'],
            'cu' => ['nullable', 'numeric'],
            'zn' => ['nullable', 'numeric'],
            'mn' => ['nullable', 'numeric'],
            'co' => ['nullable', 'numeric'],
            'se' => ['nullable', 'numeric'],
            'i' => ['nullable', 'numeric'],
            'molybdene' => ['nullable', 'numeric'],
            'vit_a' => ['nullable', 'numeric'],
            'vit_d' => ['nullable', 'numeric'],
            'vit_e' => ['nullable', 'numeric'],
            'c6_10' => ['nullable', 'numeric'],
            'c12_0' => ['nullable', 'numeric'],
            'c14_0' => ['nullable', 'numeric'],
            'c16_0' => ['nullable', 'numeric'],
            'c16_1' => ['nullable', 'numeric'],
            'c18_0' => ['nullable', 'numeric'],
            'c18_1' => ['nullable', 'numeric'],
            'c18_2' => ['nullable', 'numeric'],
            'c18_3' => ['nullable', 'numeric'],
            'c20_0' => ['nullable', 'numeric'],
            'c20_1' => ['nullable', 'numeric'],
            'c22_0' => ['nullable', 'numeric'],
            'c22_1' => ['nullable', 'numeric'],
            'c24_0' => ['nullable', 'numeric'],
            'b_vec' => ['nullable', 'numeric'],
            'ufl2007' => ['nullable', 'numeric'],
            'ufv2007' => ['nullable', 'numeric'],
            'pdia2007' => ['nullable', 'numeric'],
            'pdie2007' => ['nullable', 'numeric'],
            'pdin2007' => ['nullable', 'numeric'],
            'd_mo2007' => ['nullable', 'numeric'],
            'd_ma2007' => ['nullable', 'numeric'],
            'd_cb2007' => ['nullable', 'numeric'],
            'd_ndf2007' => ['nullable', 'numeric'],
            'd_adf2007' => ['nullable', 'numeric'],
            'uem2007' => ['nullable', 'numeric'],
            'uel2007' => ['nullable', 'numeric'],
            'ueb2007' => ['nullable', 'numeric'],
            'eb2007' => ['nullable', 'numeric'],
            'd_e2007' => ['nullable', 'numeric'],
            'em2007' => ['nullable', 'numeric'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            self::appendContractErrors($validator, $this->all());
        }];
    }

    /** @param array<string, mixed> $data */
    public static function appendContractErrors(Validator $validator, array $data): void
    {
        $value = static fn (string $field): mixed => $data[$field] ?? null;

        foreach (['ms', 'mo', 'mat', 'cb', 'ndf', 'adf', 'adl', 'ee', 'ag', 'eb', 'em', 'amidon', 'sucres', 'pf', 'ufl', 'ufv', 'uem', 'uel', 'ueb', 'pdia', 'pdi', 'ca', 'caabs', 'p', 'pabs', 'mg', 'na', 'k', 'cl', 's', 'cu', 'zn', 'mn', 'co', 'se', 'i', 'molybdene', 'vit_a', 'vit_d', 'vit_e'] as $field) {
            if ($value($field) !== null && $value($field) !== '' && (float) $value($field) < 0) {
                $validator->errors()->add($field, 'La valeur ne peut pas être négative.');
            }
        }
        foreach (['ms', 'd_mo', 'd_ma', 'd_cb', 'd_ndf', 'd_adf', 'd_e', 'dt_n', 'dt6_n', 'dr_n', 'dt_ami', 'dt6_ami', 'dt_ms', 'dt6_ms'] as $field) {
            if ($value($field) !== null && $value($field) !== '' && ((float) $value($field) < 0 || (float) $value($field) > 100)) {
                $validator->errors()->add($field, 'La valeur doit être comprise entre 0 et 100 %.');
            }
        }

        $type = (string) $value('type');
        if ($type === '') {
            return;
        }
        if ($type === 'Mineral') {
            if ($value('procede_technologique') !== 'mineral') {
                $validator->errors()->add('procede_technologique', 'Une source minérale doit utiliser le procédé « mineral ».');
            }

            return;
        }

        foreach (['famille_botanique', 'procede_technologique'] as $field) {
            if ($value($field) === null || $value($field) === '') {
                $validator->errors()->add($field, 'La classification botanique et technologique est obligatoire.');
            }
        }

        $dynamicFields = ['mo', 'mat', 'd_mo', 'eb', 'dt6_n', 'dr_n'];
        $tabulatedFields = ['ms', 'ufl', 'ufv', 'pdi', 'uel', 'uem', 'ueb'];
        $complete = static fn (array $fields): bool => collect($fields)->every(
            fn (string $field): bool => $value($field) !== null && $value($field) !== ''
        );

        if (! $complete($dynamicFields) && ! $complete($tabulatedFields)) {
            $validator->errors()->add(
                'ufl',
                'Renseignez soit la voie tabulée complète (MS, UFL, UFV, PDI, UEL/UEM/UEB), soit tous les précurseurs du recalcul (MO, MAT, dMO, EB, DT6N, drN).'
            );
        }
    }
}
