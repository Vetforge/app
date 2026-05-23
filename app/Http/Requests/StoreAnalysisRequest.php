<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Breeder;
use App\Support\VeterinaryModules;
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
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
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
            },
        ];
    }
}
