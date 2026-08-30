<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EventResult extends Model
{
    protected $fillable = ['event_id', 'member_id', 'position', 'ce_awarded'];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'ce_awarded' => 'integer',
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
