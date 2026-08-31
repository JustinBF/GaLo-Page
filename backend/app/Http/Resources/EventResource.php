<?php

namespace App\Http\Resources;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Event
 */
class EventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'held_at' => $this->held_at?->toDateString(),
            'difficulty' => $this->difficulty,
            'prize_value' => $this->prize_value,
            'co_awarded' => $this->co_awarded,
            'co_manual_override' => $this->co_manual_override,
            'notes' => $this->notes,

            'organizers' => $this->whenLoaded('organizers', fn () => $this->organizers->map(
                fn ($organizer) => [
                    'id' => $organizer->id,
                    'nick' => $organizer->nick,
                    'co_share' => $organizer->pivot->co_share,
                    'has_avatar' => $organizer->hasAvatar(),
                    'avatar_version' => $organizer->updated_at?->timestamp,
                ],
            )),

            // position null = insignia general del evento.
            'badges' => $this->whenLoaded('badges', fn () => $this->badges->map(
                fn ($badge) => [
                    'position' => $badge->position,
                    'version' => $badge->updated_at?->timestamp,
                ],
            )),

            'results' => $this->whenLoaded('results', fn () => $this->results->map(
                fn ($result) => [
                    'position' => $result->position,
                    'ce_awarded' => $result->ce_awarded,
                    'member' => [
                        'id' => $result->member->id,
                        'nick' => $result->member->nick,
                        'has_avatar' => $result->member->hasAvatar(),
                        'avatar_version' => $result->member->updated_at?->timestamp,
                    ],
                ],
            )),

            'total_ce_awarded' => $this->whenLoaded(
                'results',
                fn () => $this->results->sum('ce_awarded'),
            ),
        ];
    }
}
