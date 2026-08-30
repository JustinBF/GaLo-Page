<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreEventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'type' => ['required', Rule::in(['torneo', 'caza', 'sorteo', 'otro'])],
            'held_at' => ['required', 'date'],
            'difficulty' => ['required', Rule::in(['baja', 'media', 'alta', 'extrema'])],
            // En Pokeyenes crudos: el frontend convierte "1.5m" a 1500000.
            'prize_value' => ['required', 'integer', 'min:0', 'max:999999999999'],
            'organizer_id' => ['nullable', 'integer', Rule::exists('members', 'id')],

            // Si va a null, se aplica la regla automática de co_rules.
            'co_awarded' => ['nullable', 'integer', 'min:0', 'max:100000'],

            'notes' => ['nullable', 'string', 'max:500'],

            // Podio. El CE lo decide el admin según la dificultad.
            'results' => ['array', 'max:3'],
            'results.*.member_id' => ['required', 'integer', Rule::exists('members', 'id')],
            'results.*.position' => ['required', 'integer', 'between:1,3'],
            'results.*.ce_awarded' => ['required', 'integer', 'min:0', 'max:100000'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $results = $this->input('results', []);

                $positions = array_column($results, 'position');
                if (count($positions) !== count(array_unique($positions))) {
                    $validator->errors()->add('results', 'Hay dos jugadores en la misma posición.');
                }

                $members = array_column($results, 'member_id');
                if (count($members) !== count(array_unique($members))) {
                    $validator->errors()->add('results', 'Un jugador no puede ocupar dos posiciones.');
                }
            },
        ];
    }
}
