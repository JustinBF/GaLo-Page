<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'nick' => ['required', 'string', 'max:40', Rule::unique('members', 'nick')],
            'rank_id' => ['nullable', 'integer', Rule::exists('ranks', 'id')],
            // El rango de organizador solo se obtiene canjeando CO en la tienda,
            // por eso no se acepta aquí.
            'is_player' => ['boolean'],
            'is_organizer' => ['boolean'],
            'is_active' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'nick.unique' => 'Ya existe un miembro con ese nick.',
        ];
    }
}
