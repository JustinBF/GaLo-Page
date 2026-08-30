<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Rank extends Model
{
    protected $fillable = [
        'name', 'slug', 'level', 'scope', 'color_hex', 'icon_mime', 'icon_data',
    ];

    protected $hidden = ['icon_data'];

    protected function casts(): array
    {
        return ['level' => 'integer'];
    }

    public function playerMembers(): HasMany
    {
        return $this->hasMany(Member::class, 'rank_id');
    }

    public function organizerMembers(): HasMany
    {
        return $this->hasMany(Member::class, 'organizer_rank_id');
    }
}
