<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Enums\TypeAliment;
use App\Http\Requests\StoreAlimentRequest as BaseStoreAlimentRequest;
use Illuminate\Validation\Rule;

class StoreAlimentRequest extends BaseStoreAlimentRequest
{
    public function rules(): array
    {
        return [
            'code_inra' => ['nullable', 'string', 'unique:aliments,code_inra'],
            'user_id' => ['nullable', 'exists:users,id'],
            'libelle0' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', Rule::enum(TypeAliment::class)],
        ];
    }

    public function after(): array
    {
        return [];
    }
}
