<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Member extends Model
{
    protected $fillable = [
        'nick', 'rank_id', 'organizer_rank_id', 'is_player', 'is_organizer',
        'is_active', 'avatar_mime', 'avatar_data', 'notes',
    ];

    // Nunca en listados: son blobs. Se sirven por su propio endpoint.
    protected $hidden = ['avatar_data'];

    protected function casts(): array
    {
        return [
            'is_player' => 'boolean',
            'is_organizer' => 'boolean',
            'is_active' => 'boolean',
            'ce_balance' => 'integer',
            'co_balance' => 'integer',
        ];
    }

    public function rank(): BelongsTo
    {
        return $this->belongsTo(Rank::class, 'rank_id');
    }

    public function organizerRank(): BelongsTo
    {
        return $this->belongsTo(Rank::class, 'organizer_rank_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(CreditTransaction::class);
    }

    public function results(): HasMany
    {
        return $this->hasMany(EventResult::class);
    }

    public function organizedEvents(): HasMany
    {
        return $this->hasMany(Event::class, 'organizer_id');
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(Redemption::class);
    }

    public function scopePlayers(Builder $q): Builder
    {
        return $q->where('is_player', true)->where('is_active', true);
    }

    public function scopeOrganizers(Builder $q): Builder
    {
        return $q->where('is_organizer', true)->where('is_active', true);
    }

    public function hasAvatar(): bool
    {
        return $this->avatar_mime !== null;
    }
}
