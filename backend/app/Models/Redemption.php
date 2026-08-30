<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Redemption extends Model
{
    protected $fillable = [
        'member_id', 'reward_id', 'reward_name', 'currency',
        'cost_paid', 'status', 'processed_by', 'note',
    ];

    protected function casts(): array
    {
        return ['cost_paid' => 'integer'];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function reward(): BelongsTo
    {
        return $this->belongsTo(Reward::class);
    }

    public function processor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(CreditTransaction::class);
    }
}
