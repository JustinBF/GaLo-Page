<?php

namespace App\Http\Resources;

use App\Models\EventResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Un podio del miembro dentro de un evento: en qué quedó y qué se llevó.
 *
 * @mixin EventResult
 */
class MemberResultResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'position' => $this->position,
            'ce_awarded' => $this->ce_awarded,
            'event' => [
                'id' => $this->event->id,
                'name' => $this->event->name,
                'type' => $this->event->type,
                'held_at' => $this->event->held_at?->toDateString(),
                'difficulty' => $this->event->difficulty,
            ],
        ];
    }
}
