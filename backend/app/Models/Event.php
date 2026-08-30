<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Event extends Model
{
    protected $fillable = [
        'name', 'type', 'held_at', 'difficulty', 'prize_value',
        'organizer_id', 'co_awarded', 'co_manual_override', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'held_at' => 'date',
            'prize_value' => 'integer',
            'co_awarded' => 'integer',
            'co_manual_override' => 'boolean',
        ];
    }

    public function organizer(): BelongsTo
    {
        return $this->belongsTo(Member::class, 'organizer_id');
    }

    public function results(): HasMany
    {
        return $this->hasMany(EventResult::class)->orderBy('position');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(CreditTransaction::class);
    }
}
