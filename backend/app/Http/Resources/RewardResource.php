<?php

namespace App\Http\Resources;

use App\Models\Reward;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Reward
 */
class RewardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'currency' => $this->currency,
            'cost' => $this->cost,
            'category' => $this->category,
            'stock' => $this->stock,
            'in_stock' => $this->isInStock(),
            'is_active' => $this->is_active,
            'is_featured' => $this->is_featured,
            'sort_order' => $this->sort_order,

            'grants_rank' => $this->whenLoaded(
                'grantsRank',
                fn () => $this->grantsRank ? [
                    'id' => $this->grantsRank->id,
                    'name' => $this->grantsRank->name,
                    'color_hex' => $this->grantsRank->color_hex,
                ] : null,
            ),

            'has_image' => $this->hasImage(),
            'image_version' => $this->updated_at?->timestamp,
        ];
    }
}
