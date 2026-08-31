<?php

namespace App\Http\Resources;

use App\Models\Member;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Member
 */
class MemberResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nick' => $this->nick,
            'is_player' => $this->is_player,
            'is_organizer' => $this->is_organizer,
            'is_active' => $this->is_active,
            'notes' => $this->notes,

            'rank' => $this->whenLoaded('rank', fn () => $this->rankPayload($this->rank)),
            'organizer_rank' => $this->whenLoaded(
                'organizerRank',
                fn () => $this->rankPayload($this->organizerRank),
            ),

            'ce_balance' => $this->ce_balance,
            'co_balance' => $this->co_balance,

            // Solo presentes en los listados que piden agregados.
            'top1' => $this->whenNotNull($this->top1_count),
            'top2' => $this->whenNotNull($this->top2_count),
            'top3' => $this->whenNotNull($this->top3_count),
            'events_organized' => $this->whenNotNull($this->organized_events_shared_count),
            'prizes_total' => $this->whenNotNull($this->organized_events_shared_sum_prize_value),

            'has_avatar' => $this->hasAvatar(),
            'avatar_url' => $this->hasAvatar()
                ? route('members.avatar', ['member' => $this->id])
                : null,
            // Cambia al subir un avatar nuevo: rompe la cache del navegador.
            'avatar_version' => $this->updated_at?->timestamp,
        ];
    }

    private function rankPayload(mixed $rank): ?array
    {
        if ($rank === null) {
            return null;
        }

        return [
            'id' => $rank->id,
            'name' => $rank->name,
            'slug' => $rank->slug,
            'level' => $rank->level,
            'color_hex' => $rank->color_hex,
            // Sin esto el badge nunca pinta el icono subido por el admin.
            'has_icon' => $rank->icon_mime !== null,
            'icon_version' => $rank->updated_at?->timestamp,
        ];
    }
}
