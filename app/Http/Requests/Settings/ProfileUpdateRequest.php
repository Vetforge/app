<?php

declare(strict_types=1);

namespace App\Http\Requests\Settings;

use App\Concerns\ProfileValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ProfileUpdateRequest extends FormRequest
{
    use ProfileValidationRules;

    protected function prepareForValidation(): void
    {
        $profile = $this->input('clinic_profile', []);

        if (! is_array($profile)) {
            $this->merge(['clinic_profile' => null]);

            return;
        }

        $normalized = collect(['name', 'address', 'postal_code', 'city', 'phone', 'email'])
            ->mapWithKeys(function (string $field) use ($profile): array {
                $value = $profile[$field] ?? null;

                if (is_string($value)) {
                    $value = trim($value);
                }

                return [$field => $value === '' ? null : $value];
            })
            ->all();

        $this->merge([
            'clinic_profile' => collect($normalized)->every(fn ($value): bool => $value === null) ? null : $normalized,
        ]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->profileRules($this->user()->id);
    }
}
