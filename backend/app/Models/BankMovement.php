<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Libro del Banco del Team. El saldo es la suma de estos movimientos.
 */
class BankMovement extends Model
{
    protected $fillable = ['contributor_name', 'amount', 'description', 'created_by'];

    protected function casts(): array
    {
        return ['amount' => 'integer'];
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public static function balance(): int
    {
        return (int) static::sum('amount');
    }
}
