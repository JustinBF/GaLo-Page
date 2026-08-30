<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Reward extends Model
{
    protected $fillable = [
        'name', 'description', 'currency', 'cost', 'category', 'grants_rank_id',
        'image_mime', 'image_data', 'stock', 'is_active', 'is_featured', 'sort_order',
    ];

    protected $hidden = ['image_data'];

    protected function casts(): array
    {
        return [
            'cost' => 'integer',
            'stock' => 'integer',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function grantsRank(): BelongsTo
    {
        return $this->belongsTo(Rank::class, 'grants_rank_id');
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(Redemption::class);
    }

    public function scopeShop(Builder $q, string $currency): Builder
    {
        return $q->where('currency', $currency)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('cost');
    }

    public function hasImage(): bool
    {
        return $this->image_mime !== null;
    }

    public function isInStock(): bool
    {
        return $this->stock === null || $this->stock > 0;
    }
}
