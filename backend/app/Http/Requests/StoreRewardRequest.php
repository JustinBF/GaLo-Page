<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreRewardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:500'],
            'currency' => ['required', Rule::in(['CE', 'CO'])],
            'cost' => ['required', 'integer', 'min:0', 'max:1000000'],
            'category' => [
                'required',
                Rule::in(['pokemon', 'objeto', 'cosmetico', 'ascenso_rango', 'especial']),
            ],
            'grants_rank_id' => ['nullable', 'integer', Rule::exists('ranks', 'id')],
            // null = stock ilimitado
            'stock' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'is_active' => ['boolean'],
            'is_featured' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                // Los ascensos de rango son la recompensa de los organizadores.
                if ($this->input('grants_rank_id') && $this->input('currency') !== 'CO') {
                    $validator->errors()->add(
                        'grants_rank_id',
                        'Los ascensos de rango solo se canjean con CO.',
                    );
                }

                if ($this->input('category') === 'ascenso_rango' && ! $this->input('grants_rank_id')) {
                    $validator->errors()->add(
                        'grants_rank_id',
                        'Elige qué rango otorga este premio.',
                    );
                }
            },
        ];
    }
}
