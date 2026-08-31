<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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

    /**
     * El CO del evento se reparte entre todos ellos. La parte de cada uno
     * vive en el pivote porque el resto de la division no cuadra a partes
     * exactamente iguales.
     */
    public function organizers(): BelongsToMany
    {
        return $this->belongsToMany(Member::class, 'event_organizer')
            ->withPivot('co_share', 'prize_share')
            ->withTimestamps();
    }

    public function badges(): HasMany
    {
        return $this->hasMany(EventBadge::class);
    }

    /**
     * La insignia que le corresponde a un puesto: la especifica si existe,
     * si no la general del evento.
     */
    public function badgeFor(int $position): ?EventBadge
    {
        return $this->badges->firstWhere('position', $position)
            ?? $this->badges->firstWhere('position', null);
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
