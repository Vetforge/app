<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePlanRationnementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nom' => ['required', 'string', 'max:255'],
            'date' => ['required', 'date'],
            'inra' => ['required', Rule::in(['2007', '2018'])],
            'breeder_id' => [
                'required',
                'integer',
                Rule::exists('breeders', 'id')->where('user_id', $this->user()->id),
            ],
        ];
    }
}
