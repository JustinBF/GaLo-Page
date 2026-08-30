<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditTransaction extends Model
{
    protected $fillable = [
        'member_id', 'currency', 'amount', 'reason',
        'event_id', 'redemption_id', 'note', 'created_by',
    ];

    protected function casts(): array
    {
        return ['amount' => 'integer'];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function redemption(): BelongsTo
    {
        return $this->belongsTo(Redemption::class);
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
