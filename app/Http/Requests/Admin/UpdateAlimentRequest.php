<?php

declare(strict_types=1);

namespace App\Http\Requests\Admin;

use App\Http\Requests\UpdateAlimentRequest as BaseUpdateAlimentRequest;
use Illuminate\Validation\Rule;

class UpdateAlimentRequest extends BaseUpdateAlimentRequest
{
    public function rules(): array
    {
        $aliment = $this->route('aliment');

        return [
            ...self::sharedRules(),
            'user_id' => ['nullable', 'exists:users,id'],
            'breeder_id' => $aliment->code_inra !== null
                ? ['prohibited']
                : ['nullable', 'integer', Rule::exists('breeders', 'id')->where('user_id', $aliment->user_id ?? $this->user()->id)],
        ];
    }
}
