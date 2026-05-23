<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePlanRationnementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nom' => ['required', 'string', 'max:255'],
            'date' => ['nullable', 'date'],
            'breeder_id' => [
                'nullable',
                'integer',
                Rule::exists('breeders', 'id')->where('user_id', $this->user()->id),
            ],
        ];
    }
}
