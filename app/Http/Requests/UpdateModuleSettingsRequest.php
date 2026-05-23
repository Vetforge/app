<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Support\VeterinaryModules;
use Illuminate\Foundation\Http\FormRequest;

class UpdateModuleSettingsRequest extends FormRequest
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
            'settings' => ['required', 'array'],
        ];
    }
}
