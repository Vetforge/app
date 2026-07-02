<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Analysis;
use App\Models\Breeder;
use App\Support\VeterinaryModules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreAnalysisRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return in_array((string) $this->route('module'), VeterinaryModules::slugs(), true);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'breeder_id' => ['required', 'integer', Rule::exists('breeders', 'id')],
            'animal_nom' => ['nullable', 'string', 'max:255'],
            'sampled_at' => ['nullable', 'date'],
            'analyzed_at' => ['nullable', 'date'],
            'intervenant' => ['nullable', 'string', 'max:255'],
            'payload' => ['required', 'array'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $belongsToUser = Breeder::query()
                    ->where('id', $this->input('breeder_id'))
                    ->where('user_id', $this->user()->id)
                    ->exists();

                if (! $belongsToUser) {
                    $validator->errors()->add('breeder_id', 'Cet eleveur est introuvable.');
                }

                $this->validateComparisonAnalysis($validator);
            },
        ];
    }

    private function validateComparisonAnalysis(Validator $validator): void
    {
        $module = (string) $this->route('module');

        if (! in_array($module, ['bse-laitier', 'bse-allaitant'], true)) {
            return;
        }

        $comparisonId = $this->input('payload.comparison_analysis_id');

        if ($comparisonId === null || $comparisonId === '' || $comparisonId === 0 || $comparisonId === '0') {
            return;
        }

        if (! is_numeric($comparisonId)) {
            $validator->errors()->add('payload.comparison_analysis_id', 'Ancien bilan invalide.');

            return;
        }

        $exists = Analysis::query()
            ->where('id', (int) $comparisonId)
            ->where('user_id', $this->user()->id)
            ->where('module', $module)
            ->where('breeder_id', $this->input('breeder_id'))
            ->exists();

        if (! $exists) {
            $validator->errors()->add('payload.comparison_analysis_id', 'Ancien bilan introuvable pour cet eleveur.');
        }
    }
}
