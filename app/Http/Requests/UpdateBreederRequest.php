<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Models\Breeder;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBreederRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $breeder = $this->route('breeder');

        return $breeder instanceof Breeder && $breeder->user_id === $this->user()?->id;
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
        $breeder = $this->route('breeder');
        $breederId = $breeder instanceof Breeder ? $breeder->id : null;

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('breeders', 'name')->where('user_id', $this->user()->id)->ignore($breederId),
            ],
            'address' => ['nullable', 'string', 'max:255'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'city' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'herd_number' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
