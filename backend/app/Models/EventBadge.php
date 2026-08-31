<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventBadge extends Model
{
    protected $fillable = ['event_id', 'position', 'mime', 'data'];

    // Nunca en listados: es un blob. Se sirve por su propio endpoint.
    protected $hidden = ['data'];

    protected function casts(): array
    {
        return ['position' => 'integer'];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
