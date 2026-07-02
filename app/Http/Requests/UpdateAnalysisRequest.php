<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Analysis;
use App\Models\Breeder;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateAnalysisRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $analysis = $this->route('analysis');

        return $analysis instanceof Analysis && $analysis->user_id === $this->user()?->id;
    }

    /**
     * Renvoyer un 404 plutôt qu'un 403 pour ne pas révéler l'existence de la ressource.
     */
    protected function failedAuthorization(): void
    {
        abort(404);
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
        $analysis = $this->route('analysis');

        if (! $analysis instanceof Analysis || ! in_array($analysis->module, ['bse-laitier', 'bse-allaitant'], true)) {
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

        $comparisonId = (int) $comparisonId;

        if ($comparisonId === $analysis->id) {
            $validator->errors()->add('payload.comparison_analysis_id', 'Un bilan ne peut pas etre compare a lui-meme.');

            return;
        }

        $exists = Analysis::query()
            ->where('id', $comparisonId)
            ->where('user_id', $this->user()->id)
            ->where('module', $analysis->module)
            ->where('breeder_id', $this->input('breeder_id'))
            ->exists();

        if (! $exists) {
            $validator->errors()->add('payload.comparison_analysis_id', 'Ancien bilan introuvable pour cet eleveur.');
        }
    }
}
