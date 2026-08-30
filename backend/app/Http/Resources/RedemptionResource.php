<?php

namespace App\Http\Resources;

use App\Models\Redemption;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Redemption
 */
class RedemptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            // Nombre y coste congelados al momento del canje.
            'reward_name' => $this->reward_name,
            'currency' => $this->currency,
            'cost_paid' => $this->cost_paid,
            'status' => $this->status,
            'note' => $this->note,
            'created_at' => $this->created_at?->toDateTimeString(),

            'member' => $this->whenLoaded('member', fn () => [
                'id' => $this->member->id,
                'nick' => $this->member->nick,
                'has_avatar' => $this->member->hasAvatar(),
                'avatar_version' => $this->member->updated_at?->timestamp,
            ]),

            // El premio pudo borrarse despues del canje: puede venir null.
            'reward' => $this->whenLoaded(
                'reward',
                fn () => $this->reward ? [
                    'id' => $this->reward->id,
                    'category' => $this->reward->category,
                    'has_image' => $this->reward->hasImage(),
                    'image_version' => $this->reward->updated_at?->timestamp,
                ] : null,
            ),

            'by' => $this->whenLoaded('processor', fn () => $this->processor?->username),
        ];
    }
}
