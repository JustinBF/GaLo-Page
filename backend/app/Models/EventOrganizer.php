<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Fila del pivote entre evento y organizador.
 *
 * Existe como modelo propio para poder agregar sobre sus columnas: el CO y
 * el premio que le tocaron a cada uno viven aqui, no en events.
 */
class EventOrganizer extends Model
{
    protected $table = 'event_organizer';

    protected $fillable = ['event_id', 'member_id', 'co_share', 'prize_share'];

    protected function casts(): array
    {
        return [
            'co_share' => 'integer',
            'prize_share' => 'integer',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }
}
