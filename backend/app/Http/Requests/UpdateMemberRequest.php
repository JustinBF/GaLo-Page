<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'nick' => [
                'sometimes', 'required', 'string', 'max:40',
                Rule::unique('members', 'nick')->ignore($this->route('member')->id),
            ],
            'rank_id' => ['nullable', 'integer', Rule::exists('ranks', 'id')],
            'organizer_rank_id' => ['nullable', 'integer', Rule::exists('ranks', 'id')],
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
